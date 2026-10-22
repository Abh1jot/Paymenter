<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Livewire;

use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Paymenter\Extensions\Servers\PterodactylV2\DTO\ResourceUsage;
use Paymenter\Extensions\Servers\PterodactylV2\DTO\ServerDetails;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PasswordService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PowerService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PterodactylClient;
use Paymenter\Extensions\Servers\PterodactylV2\Services\ServerResolver;

/**
 * Customer and administrator server management Livewire component.
 *
 * Provides:
 * - Live power controls (Start, Restart, Stop, Kill)
 * - Real-time resource monitors (CPU, Memory, Disk, Network, Uptime)
 * - Server credentials and one-click password rotation
 * - Deep link to Pterodactyl and Wemx SSO integration
 */
class ServerManager extends Component
{
    public int $serviceId;
    public string $activeTab = 'controls';
    public array $credentials = [];
    public ?array $resources = null;
    public string $currentState = 'offline';
    public bool $showPassword = false;
    public ?string $newPassword = null;
    public bool $passwordUpdated = false;
    public ?string $actionLoading = null;
    public ?string $statusMessage = null;
    public string $statusType = 'success';
    public bool $clientApiAvailable = false;
    public bool $ssoAvailable = false;
    public ?string $ssoUrl = null;
    public ?string $panelUrl = null;
    public bool $loadFailed = false;
    public ?string $loadErrorMessage = null;

    public function mount($service): void
    {
        $this->serviceId = is_object($service) ? (int) $service->id : (int) $service;

        // Security authorization check
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
            abort(403, 'You do not have permission to manage this service.');
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
     * Load initial server data, credentials, and telemetry.
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

            $this->clientApiAvailable = $client->hasClientKey();
            $this->panelUrl = $server->panelUrl;

            $config = $service->product->server->settings->pluck('value', 'key')->toArray();
            $this->ssoAvailable = !empty($config['sso_secret']);
            if ($this->ssoAvailable) {
                $this->ssoUrl = route('pterodactylv2.sso-login', ['serviceId' => $service->id]);
            }

            // Populate Credentials
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
                'password' => $this->newPassword ?: $storedPassword,
                'memory_limit' => $server->memoryLimit,
                'disk_limit' => $server->diskLimit,
                'cpu_limit' => $server->cpuLimit,
            ];

            // Fetch live telemetry if Client API key is configured
            if ($this->clientApiAvailable) {
                $usage = PowerService::getResourceUsage($service, $server, $client);
                if ($usage) {
                    $this->currentState = $usage->currentState;
                    $this->resources = [
                        'current_state' => $usage->currentState,
                        'is_suspended' => $usage->isSuspended,
                        'memory_mb' => $usage->memoryMb,
                        'memory_limit_mb' => $usage->memoryLimitMb,
                        'memory_percentage' => $usage->memoryPercentage,
                        'cpu_absolute' => $usage->cpuAbsolute,
                        'cpu_limit' => $usage->cpuLimit,
                        'cpu_percentage' => $usage->cpuPercentage,
                        'disk_mb' => $usage->diskMb,
                        'disk_limit_mb' => $usage->diskLimitMb,
                        'disk_percentage' => $usage->diskPercentage,
                        'network_rx' => $usage->networkRxFormatted,
                        'network_tx' => $usage->networkTxFormatted,
                        'uptime' => $usage->uptimeFormatted,
                    ];
                }
            } else {
                $this->currentState = $server->isSuspended ? 'suspended' : ($server->isInstalling ? 'installing' : 'active');
            }
        } catch (Exception $e) {
            $this->loadFailed = true;
            $this->loadErrorMessage = $e->getMessage();
        }
    }

    /**
     * Polling method called periodically by wire:poll to refresh telemetry.
     */
    public function pollResources(): void
    {
        if (!$this->clientApiAvailable || $this->loadFailed) {
            return;
        }

        try {
            $service = $this->authorizeServiceAccess();
            $client = $this->getClient($service);
            if ($client) {
                $server = ServerResolver::resolve($service, $client, failIfNotFound: false);
                if ($server) {
                    $usage = PowerService::getResourceUsage($service, $server, $client);
                    if ($usage) {
                        $this->currentState = $usage->currentState;
                        $this->resources = [
                            'current_state' => $usage->currentState,
                            'is_suspended' => $usage->isSuspended,
                            'memory_mb' => $usage->memoryMb,
                            'memory_limit_mb' => $usage->memoryLimitMb,
                            'memory_percentage' => $usage->memoryPercentage,
                            'cpu_absolute' => $usage->cpuAbsolute,
                            'cpu_limit' => $usage->cpuLimit,
                            'cpu_percentage' => $usage->cpuPercentage,
                            'disk_mb' => $usage->diskMb,
                            'disk_limit_mb' => $usage->diskLimitMb,
                            'disk_percentage' => $usage->diskPercentage,
                            'network_rx' => $usage->networkRxFormatted,
                            'network_tx' => $usage->networkTxFormatted,
                            'uptime' => $usage->uptimeFormatted,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Silently ignore transient poll errors
        }
    }

    /**
     * Dispatch a power action to the server (start, stop, restart, kill).
     */
    public function powerAction(string $signal): void
    {
        $this->actionLoading = $signal;
        $this->statusMessage = null;

        try {
            $service = $this->authorizeServiceAccess();
            $client = $this->getClient($service);

            if (!$client) {
                throw new Exception('Pterodactyl configuration missing.');
            }

            PowerService::sendPowerAction($service, $signal, $client);

            $actionLabels = [
                'start' => 'Server boot signal sent.',
                'restart' => 'Server restart signal sent.',
                'stop' => 'Server graceful stop signal sent.',
                'kill' => 'Server kill signal dispatched.',
            ];

            $this->statusType = 'success';
            $this->statusMessage = $actionLabels[$signal] ?? "Power action '{$signal}' sent successfully.";

            // Fast refresh
            $this->pollResources();
        } catch (Exception $e) {
            $this->statusType = 'error';
            $this->statusMessage = 'Failed to execute power action: ' . $e->getMessage();
        } finally {
            $this->actionLoading = null;
        }
    }

    /**
     * Toggle visibility of the password on the credentials card.
     */
    public function togglePassword(): void
    {
        $this->showPassword = !$this->showPassword;
    }

    /**
     * Generate a new panel password for the customer and sync it to Pterodactyl.
     */
    public function generatePassword(): void
    {
        $this->actionLoading = 'password';
        $this->statusMessage = null;

        try {
            $service = $this->authorizeServiceAccess();
            $client = $this->getClient($service);

            if (!$client) {
                throw new Exception('Pterodactyl configuration missing.');
            }

            $password = PasswordService::generateAndSyncPassword($service, $client);
            $this->newPassword = $password;
            $this->credentials['password'] = $password;
            $this->showPassword = true;
            $this->passwordUpdated = true;
            $this->statusType = 'success';
            $this->statusMessage = 'Password regenerated and updated on Pterodactyl panel successfully.';
        } catch (Exception $e) {
            $this->statusType = 'error';
            $this->statusMessage = 'Failed to generate password: ' . $e->getMessage();
        } finally {
            $this->actionLoading = null;
        }
    }

    /**
     * Switch view tabs.
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('pterodactylv2::livewire.server-manager');
    }
}
