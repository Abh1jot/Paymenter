<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Livewire;

use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PasswordService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PterodactylClient;
use Paymenter\Extensions\Servers\PterodactylV2\Services\ServerResolver;

/**
 * Clean, lightweight server details Livewire component for customer service pages.
 *
 * Displays:
 * - Server name, identifier, and connection address (IP:Port)
 * - Pterodactyl node, allocated memory, disk, and CPU specs
 * - Panel username, account email, and direct panel links
 * - Password setup & reset instructions with panel deep-link
 */
class ServerManager extends Component
{
    public int $serviceId;
    public array $credentials = [];
    public string $currentState = 'active';
    public bool $showPassword = false;
    public ?string $panelUrl = null;
    public ?string $resetPasswordUrl = null;
    public bool $ssoAvailable = false;
    public ?string $ssoUrl = null;
    public bool $loadFailed = false;
    public ?string $loadErrorMessage = null;

    public function mount($service): void
    {
        $this->serviceId = is_object($service) ? (int) $service->id : (int) $service;

        $this->authorizeServiceAccess();
        $this->loadData();
    }

    /**
     * Enforce strict customer ownership authorization.
     */
    protected function authorizeServiceAccess(): Service
    {
        if (!Auth::check()) {
            abort(403, 'Unauthorized access.');
        }

        $service = Service::with(['product.server.settings', 'user', 'properties'])->findOrFail($this->serviceId);

        $currentUser = Auth::user();
        $isOwner = (int) $service->user_id === (int) $currentUser->id;
        $isAdmin = method_exists($currentUser, 'isAdmin') ? $currentUser->isAdmin() : ($currentUser->is_admin ?? false);

        if (!$isOwner && !$isAdmin) {
            abort(403, 'You do not have permission to view this service.');
        }

        return $service;
    }

    /**
     * Build the authenticated PterodactylClient instance.
     */
    protected function getClient(Service $service): ?PterodactylClient
    {
        $serverModel = $service->product?->server;
        if (!$serverModel) {
            return null;
        }

        $config = $serverModel->settings->pluck('value', 'key')->toArray();
        $host = $config['host'] ?? '';
        $apiKey = $config['api_key'] ?? '';
        $clientApiKey = $config['client_api_key'] ?? null;

        if (empty($host) || empty($apiKey)) {
            return null;
        }

        return new PterodactylClient($host, $apiKey, $clientApiKey);
    }

    /**
     * Load server attributes, allocations, and credentials.
     */
    public function loadData(): void
    {
        $this->loadFailed = false;
        $this->loadErrorMessage = null;

        try {
            $service = $this->authorizeServiceAccess();
            $client = $this->getClient($service);

            if (!$client) {
                $this->loadFailed = true;
                $this->loadErrorMessage = 'Pterodactyl server extension is not configured for this product.';
                return;
            }

            $server = ServerResolver::resolve($service, $client, failIfNotFound: false);
            if (!$server) {
                $this->loadFailed = true;
                $this->loadErrorMessage = 'Associated Pterodactyl server could not be located on the panel. If newly ordered, it may still be installing.';
                return;
            }

            $host = $client->getHost();
            $this->panelUrl = $server->panelUrl ?: rtrim($host, '/') . "/server/{$server->identifier}";
            $this->resetPasswordUrl = rtrim($host, '/') . '/auth/password';

            $config = $service->product->server->settings->pluck('value', 'key')->toArray();
            $this->ssoAvailable = !empty($config['sso_secret']);
            if ($this->ssoAvailable) {
                $this->ssoUrl = route('pterodactylv2.sso-login', ['serviceId' => $service->id]);
            }

            // Determine status based on Paymenter service status and Pterodactyl container state
            if ($service->status === 'suspended' || $server->isSuspended) {
                $this->currentState = 'suspended';
            } elseif ($server->isInstalling) {
                $this->currentState = 'installing';
            } elseif (in_array($service->status, ['cancelled', 'terminated'])) {
                $this->currentState = $service->status;
            } else {
                $this->currentState = 'active';
            }

            // Format RAM & Disk
            $ramFormatted = $server->memoryLimit > 0
                ? ($server->memoryLimit >= 1024 ? round($server->memoryLimit / 1024, 1) . ' GB' : $server->memoryLimit . ' MB')
                : 'Unlimited';

            $diskFormatted = $server->diskLimit > 0
                ? ($server->diskLimit >= 1024 ? round($server->diskLimit / 1024, 1) . ' GB' : $server->diskLimit . ' MB')
                : 'Unlimited';

            $cpuFormatted = $server->cpuLimit > 0 ? $server->cpuLimit . '%' : 'Unlimited';

            // Populate credentials
            $storedPassword = PasswordService::getStoredPassword($service);
            $this->credentials = [
                'server_name' => $server->name,
                'server_id' => $server->id,
                'uuid' => $server->uuid,
                'identifier' => $server->identifier,
                'node_name' => $server->nodeName ?: "Node #{$server->nodeId}",
                'connection' => $server->getConnectionAddress(),
                'ip' => $server->ip,
                'port' => $server->port,
                'email' => $service->user->email,
                'username' => $service->user->name ?: 'User',
                'password' => $storedPassword,
                'memory_formatted' => $ramFormatted,
                'disk_formatted' => $diskFormatted,
                'cpu_formatted' => $cpuFormatted,
            ];
        } catch (Exception $e) {
            $this->loadFailed = true;
            $this->loadErrorMessage = $e->getMessage();
        }
    }

    /**
     * Toggle visibility of the initial password if stored.
     */
    public function togglePassword(): void
    {
        $this->showPassword = !$this->showPassword;
    }

    public function render()
    {
        return view('pterodactylv2::livewire.server-manager');
    }
}
