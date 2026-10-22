<?php

namespace Paymenter\Extensions\Servers\PterodactylV2;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Server;
use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Paymenter\Extensions\Servers\PterodactylV2\Livewire\ServerManager;
use Paymenter\Extensions\Servers\PterodactylV2\Services\LifecycleService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PanelVersionDetector;
use Paymenter\Extensions\Servers\PterodactylV2\Services\ProvisioningService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PterodactylClient;
use Paymenter\Extensions\Servers\PterodactylV2\Services\ServerResolver;

/**
 * Pterodactyl v2 Extension for Paymenter with full Pterodactyl v1 and EasyPtroClient backward compatibility.
 */
#[ExtensionMeta(
    name: 'PterodactylV2',
    description: 'Production-ready Pterodactyl Panel 2.0 extension featuring live container telemetry, power controls, and seamless backward compatibility for existing services.',
    version: '2.0.0',
    author: 'Paymenter',
    url: 'https://paymenter.org/docs/extensions/pterodactyl'
)]
class PterodactylV2 extends Server
{
    /**
     * Bootstrap extension routes, namespaces, and Livewire components.
     */
    public function boot(): void
    {
        View::addNamespace('pterodactylv2', __DIR__ . '/views');
        Livewire::component('pterodactylv2.server-manager', ServerManager::class);

        // Register Artisan commands
        if (app()->runningInConsole()) {
            $this->commands([
                \Paymenter\Extensions\Servers\PterodactylV2\Console\Commands\SyncServicesCommand::class,
            ]);
        }

        // Register secure SSO auto-login route
        Route::get('/pterodactylv2/sso-login/{serviceId}', function ($serviceId) {
            if (!auth()->check()) {
                abort(403, 'Unauthorized.');
            }

            $service = Service::with(['product.server.settings', 'user'])->findOrFail($serviceId);

            // Enforce customer ownership authorization
            $currentUser = auth()->user();
            $isOwner = (int) $service->user_id === (int) $currentUser->id;
            $isAdmin = method_exists($currentUser, 'isAdmin') ? $currentUser->isAdmin() : ($currentUser->is_admin ?? false);

            if (!$isOwner && !$isAdmin) {
                abort(403, 'You do not own this service.');
            }

            $config = $service->product?->server?->settings?->pluck('value', 'key')->toArray() ?? [];
            $ssoSecret = $config['sso_secret'] ?? null;
            $host = $config['host'] ?? null;
            $apiKey = $config['api_key'] ?? null;

            if (empty($ssoSecret) || empty($host) || empty($apiKey)) {
                abort(503, 'Pterodactyl SSO is not fully configured for this server.');
            }

            $baseHost = rtrim($host, '/');

            // 1. Look up Pterodactyl User ID
            try {
                $userResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                ])->timeout(10)->get($baseHost . '/api/application/users', [
                    'filter' => ['email' => $service->user->email],
                ]);

                if (!$userResponse->successful()) {
                    abort(502, 'Could not reach Pterodactyl Application API.');
                }

                $pteroUserId = $userResponse->json('data.0.attributes.id');
            } catch (Exception $e) {
                abort(502, 'Network error while contacting Pterodactyl panel.');
            }

            if (!$pteroUserId) {
                abort(404, 'No matching Pterodactyl account found for this user.');
            }

            // 2. Dispatch SSO request (compatible with wemx/sso-pterodactyl addon)
            try {
                $ssoResponse = Http::timeout(10)->get($baseHost . '/sso-wemx', [
                    'sso_secret' => $ssoSecret,
                    'user_id' => $pteroUserId,
                ]);
            } catch (Exception $e) {
                abort(502, 'SSO login request failed.');
            }

            $ssoData = $ssoResponse->json();
            if (is_array($ssoData) && ($ssoData['success'] ?? false) === true && !empty($ssoData['redirect'])) {
                return redirect()->away($ssoData['redirect']);
            }

            $errMsg = is_array($ssoData) ? ($ssoData['message'] ?? 'SSO authentication failed.') : 'SSO authentication failed.';
            abort(502, $errMsg);
        })->middleware(['web', 'auth'])->name('pterodactylv2.sso-login');
    }

    /**
     * Build an authenticated PterodactylClient instance from current configuration or service product server.
     */
    public function getClient(?Service $service = null): PterodactylClient
    {
        $serverSettings = [];
        if ($service && $service->product?->server?->settings) {
            $serverSettings = $service->product->server->settings->pluck('value', 'key')->toArray();
        }

        $host = $serverSettings['host'] ?? $this->config('host') ?? '';
        $appKey = $serverSettings['api_key'] ?? $this->config('api_key') ?? '';
        $clientKey = $serverSettings['client_api_key'] ?? $this->config('client_api_key');

        return new PterodactylClient(
            host: (string) $host,
            appApiKey: (string) $appKey,
            clientApiKey: $clientKey
        );
    }

    /**
     * Server Extension Administrator Configuration.
     */
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'host',
                'label' => 'Pterodactyl Panel URL',
                'type' => 'text',
                'description' => 'Fully qualified URL to your Pterodactyl panel (e.g., https://panel.example.com)',
                'required' => true,
                'validation' => 'url',
            ],
            [
                'name' => 'api_key',
                'label' => 'Application API Key',
                'type' => 'text',
                'description' => 'Pterodactyl Application API key (starts with ptla_) with full administrator permissions.',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'client_api_key',
                'label' => 'Administrative Client API Key (Optional)',
                'type' => 'text',
                'description' => 'Account API key (starts with ptlc_) from an administrator account. Unlocks real-time power actions (Start/Stop/Restart/Kill) and live resource monitoring directly in Paymenter.',
                'required' => false,
                'encrypted' => true,
            ],
            [
                'name' => 'version_mode',
                'label' => 'Panel Version Mode',
                'type' => 'select',
                'description' => 'Panel 2.0 uses tags & direct eggs; Panel 1.x uses nests. Auto-Detect is recommended.',
                'options' => [
                    'auto' => 'Auto-Detect (Recommended)',
                    'v2' => 'Force Pterodactyl Panel 2.0 (v2.x)',
                    'v1' => 'Force Pterodactyl Panel 1.x (v1.x)',
                ],
                'default' => 'auto',
                'required' => true,
            ],
            [
                'name' => 'sso_secret',
                'label' => 'SSO Secret Key (Optional)',
                'type' => 'text',
                'description' => 'Secret key for Pterodactyl SSO auto-login (requires wemx/sso-pterodactyl addon). Leave blank to disable auto-login.',
                'required' => false,
                'encrypted' => true,
            ],
        ];
    }

    /**
     * Connection and validation test triggered in the administrator panel.
     */
    public function testConfig(): bool|string
    {
        $client = $this->getClient();
        $testResult = $client->testConnection();

        if ($testResult['success']) {
            return true;
        }

        return $testResult['message'] ?? 'Unable to connect to Pterodactyl panel.';
    }

    /**
     * Product Configuration fields for provisioning new servers.
     */
    public function getProductConfig($values = []): array
    {
        $client = $this->getClient();
        $version = PanelVersionDetector::detect($client, $this->config('version_mode') ?? 'auto');

        // 1. Fetch Nodes
        $nodeList = [];
        try {
            $nodes = $client->appRequest('nodes');
            foreach ($nodes['data'] ?? [] as $node) {
                $nodeList[$node['attributes']['id']] = $node['attributes']['name'];
            }
        } catch (Exception $e) {}

        // 2. Fetch Locations
        $locationList = [];
        try {
            $locations = $client->appRequest('locations');
            foreach ($locations['data'] ?? [] as $loc) {
                $locationList[$loc['attributes']['id']] = $loc['attributes']['short'] ?? $loc['attributes']['long'] ?? "Location #{$loc['attributes']['id']}";
            }
        } catch (Exception $e) {}

        // 3. Fetch Nests (Only present in v1, or legacy bridge in v2)
        $nestList = [];
        if ($version === 'v1') {
            try {
                $nests = $client->appRequest('nests');
                foreach ($nests['data'] ?? [] as $nest) {
                    $nestList[$nest['attributes']['id']] = $nest['attributes']['name'];
                }
            } catch (Exception $e) {}
        }

        // 4. Fetch Eggs
        $eggList = [];
        try {
            if ($version === 'v2') {
                // In v2, list eggs directly
                $eggs = $client->appRequest('eggs');
                foreach ($eggs['data'] ?? [] as $egg) {
                    $eggList[$egg['attributes']['id']] = $egg['attributes']['name'];
                }
            } elseif (!empty($values['nest_id'])) {
                // In v1, query under nest
                $eggs = $client->appRequest("nests/{$values['nest_id']}/eggs");
                foreach ($eggs['data'] ?? [] as $egg) {
                    $eggList[$egg['attributes']['id']] = $egg['attributes']['name'];
                }
            }
        } catch (Exception $e) {}

        $usingPortArray = !empty($values['port_array']);

        $config = [
            [
                'name' => 'location_ids',
                'label' => 'Location(s)',
                'type' => 'select',
                'description' => 'Target location(s) where the server container will be deployed. Leave unselected for automatic placement across all locations.',
                'options' => $locationList,
                'multiple' => true,
                'searchable' => true,
                'database_type' => 'array',
                'required' => false,
            ],
            [
                'name' => 'node',
                'label' => 'Specific Node (Optional)',
                'type' => 'select',
                'description' => 'Deploy directly to a specific node instead of automatic placement.',
                'options' => $nodeList,
                'required' => false,
            ],
        ];

        // Only show nest_id if on v1
        if ($version === 'v1' || !empty($nestList)) {
            $config[] = [
                'name' => 'nest_id',
                'label' => 'Nest',
                'type' => 'select',
                'options' => $nestList,
                'description' => 'Pterodactyl Nest grouping (Used on Panel 1.x).',
                'required' => ($version === 'v1'),
                'live' => true,
            ];
        }

        $config = array_merge($config, [
            [
                'name' => 'egg_id',
                'label' => 'Egg Template',
                'type' => 'select',
                'options' => $eggList,
                'description' => 'The game/application template defining Docker image, startup command, and environment.',
                'required' => true,
            ],
            [
                'name' => 'memory',
                'label' => 'Memory Limit',
                'type' => 'number',
                'suffix' => 'MiB',
                'required' => true,
                'validation' => 'numeric',
                'min_value' => 0,
                'description' => 'RAM allocation in MiB (0 for unlimited).',
            ],
            [
                'name' => 'swap',
                'label' => 'Swap Memory',
                'type' => 'number',
                'min_value' => -1,
                'suffix' => 'MiB',
                'required' => true,
                'description' => '-1 for unlimited, 0 to disable swap.',
            ],
            [
                'name' => 'disk',
                'label' => 'Disk Space',
                'type' => 'number',
                'suffix' => 'MiB',
                'required' => true,
                'min_value' => 0,
                'description' => 'Storage limit in MiB (0 for unlimited).',
            ],
            [
                'name' => 'io',
                'label' => 'Block IO Weight',
                'type' => 'number',
                'required' => true,
                'default' => 500,
                'min_value' => 10,
                'max_value' => 1000,
                'description' => 'Relative disk IO priority constraint (10 - 1000).',
            ],
            [
                'name' => 'cpu',
                'label' => 'CPU Limit',
                'type' => 'number',
                'required' => true,
                'min_value' => 0,
                'suffix' => '%',
                'description' => '100% = 1 full CPU thread (0 for unlimited).',
            ],
            [
                'name' => 'cpu_pinning',
                'label' => 'CPU Core Pinning',
                'type' => 'text',
                'description' => 'Pin container to specific CPU threads (e.g. 0,2-4,5). Leave empty for default.',
                'validation' => 'nullable|regex:/^[0-9]+(?:-[0-9]+)?(?:,[0-9]+(?:-[0-9]+)?)*$/',
            ],
            [
                'name' => 'databases',
                'label' => 'Databases Allowed',
                'type' => 'number',
                'required' => true,
                'min_value' => 0,
            ],
            [
                'name' => 'backups',
                'label' => 'Backups Allowed',
                'type' => 'number',
                'required' => true,
                'min_value' => 0,
            ],
            [
                'name' => 'additional_allocations',
                'label' => 'Additional Network Allocations',
                'type' => 'number',
                'required' => true,
                'min_value' => 0,
            ],
            [
                'name' => 'port_array',
                'label' => 'Advanced Port Array (JSON)',
                'type' => 'text',
                'description' => 'Assign specific ports to egg variables. Example: {"SERVER_PORT": 25565, "RCON_PORT": 25575}',
                'live' => true,
                'validation' => 'nullable|json',
            ],
            [
                'name' => 'port_range',
                'label' => 'Port Ranges',
                'type' => 'tags',
                'database_type' => 'array',
                'required' => false,
                'disabled' => $usingPortArray,
            ],
            [
                'name' => 'skip_scripts',
                'label' => 'Skip Egg Install Script',
                'description' => 'Check to bypass running installation scripts attached to the Egg.',
                'type' => 'checkbox',
            ],
            [
                'name' => 'dedicated_ip',
                'label' => 'Dedicated IP',
                'description' => 'Require an allocation whose IP is not shared by any other server.',
                'type' => 'checkbox',
                'disabled' => $usingPortArray,
            ],
            [
                'name' => 'start_on_completion',
                'label' => 'Start On Completion',
                'description' => 'Automatically boot server container once installation completes.',
                'type' => 'checkbox',
            ],
            [
                'name' => 'oom_killer',
                'label' => 'Enable OOM Killer',
                'description' => 'Terminate server process if it exceeds memory limit.',
                'type' => 'checkbox',
            ],
        ]);

        return $config;
    }

    /**
     * Provision a new server on Pterodactyl.
     */
    public function createServer(Service $service, $settings, $properties): array
    {
        $client = $this->getClient($service);
        $versionMode = (string) ($this->config('version_mode') ?? 'auto');

        return ProvisioningService::createServer($service, $settings, $properties, $client, $versionMode);
    }

    /**
     * Suspend a server on Pterodactyl.
     */
    public function suspendServer(Service $service, $settings, $properties): bool
    {
        $client = $this->getClient($service);
        return LifecycleService::suspend($service, $client);
    }

    /**
     * Unsuspend a server on Pterodactyl.
     */
    public function unsuspendServer(Service $service, $settings, $properties): bool
    {
        $client = $this->getClient($service);
        return LifecycleService::unsuspend($service, $client);
    }

    /**
     * Terminate and delete a server on Pterodactyl.
     */
    public function terminateServer(Service $service, $settings, $properties): bool
    {
        $client = $this->getClient($service);
        return LifecycleService::terminate($service, $client);
    }

    /**
     * Upgrade server resource limits or package startup variables.
     */
    public function upgradeServer(Service $service, $settings, $properties): bool
    {
        $client = $this->getClient($service);
        $versionMode = (string) ($this->config('version_mode') ?? 'auto');

        return LifecycleService::upgrade($service, $settings, $properties, $client, $versionMode);
    }

    /**
     * Customer service page action buttons & views.
     */
    public function getActions(Service $service): array
    {
        $client = $this->getClient($service);
        $server = ServerResolver::resolve($service, $client, failIfNotFound: false);

        $actions = [
            [
                'type' => 'view',
                'name' => 'server_manager',
                'label' => 'Server Details',
            ],
        ];

        if ($server) {
            $actions[] = [
                'type' => 'button',
                'label' => 'Go to Server',
                'url' => $server->panelUrl ?: rtrim($this->config('host'), '/') . "/server/{$server->identifier}",
                'target' => '_blank',
            ];
        }

        if (!empty($this->config('sso_secret'))) {
            $actions[] = [
                'type' => 'button',
                'label' => 'Login Panel (SSO)',
                'url' => route('pterodactylv2.sso-login', ['serviceId' => $service->id]),
                'target' => '_blank',
            ];
        }

        return $actions;
    }

    /**
     * Render the Livewire component for the service management view.
     */
    public function getView(Service $service, $settings = [], $properties = [], $viewName = null): string
    {
        if ($viewName === 'server_manager' || $viewName === 'server_credentials') {
            return Blade::render('<livewire:pterodactylv2.server-manager :service="$service" />', [
                'service' => $service,
            ]);
        }

        return '';
    }

    /**
     * Map legacy or renamed option keys.
     */
    public function migrateOption(string $key, ?string $value): array
    {
        return match ($key) {
            'egg' => ['key' => 'egg_id', 'value' => $value],
            'nest' => ['key' => 'nest_id', 'value' => $value],
            'allocation' => ['key' => 'additional_allocations', 'value' => $value],
            'location' => ['key' => 'location_ids', 'value' => json_encode([$value]), 'type' => 'array'],
            default => ['key' => $key, 'value' => $value],
        };
    }

    /**
     * Helper to register console commands from extension context.
     */
    protected function commands(array $commands): void
    {
        foreach ($commands as $command) {
            \Illuminate\Console\Application::starting(function ($artisan) use ($command) {
                $artisan->resolve($command);
            });
        }
    }
}
