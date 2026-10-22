<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Services;

use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Paymenter\Extensions\Servers\PterodactylV2\DTO\ServerDetails;

/**
 * Service to handle safe, idempotent server creation on Pterodactyl.
 */
class ProvisioningService
{
    /**
     * Create a server for a Paymenter Service.
     *
     * @throws Exception
     */
    public static function createServer(
        Service $service,
        array $settings,
        array $properties,
        PterodactylClient $client,
        string $configuredVersion = 'auto'
    ): array {
        // Idempotency check: If server already exists on Pterodactyl, return existing server to prevent duplicate creation
        $existing = ServerResolver::resolve($service, $client, failIfNotFound: false);
        if ($existing) {
            return [
                'server' => $existing->id,
                'identifier' => $existing->identifier,
                'uuid' => $existing->uuid,
                'link' => $existing->panelUrl,
            ];
        }

        $mergedSettings = array_merge($settings, $properties);
        $version = PanelVersionDetector::detect($client, $configuredVersion);

        // 1. Fetch Egg and its environment variables
        $eggData = self::resolveEggData($client, $mergedSettings, $version);
        $eggAttributes = $eggData['attributes'] ?? [];

        $environment = [];
        $variables = $eggAttributes['relationships']['variables']['data'] ?? [];
        foreach ($variables as $variable) {
            $vAttr = $variable['attributes'] ?? [];
            $envVar = $vAttr['env_variable'] ?? '';
            if ($envVar) {
                $environment[$envVar] = $mergedSettings[$envVar] ?? $vAttr['default_value'] ?? '';
            }
        }

        // Automatic fallback for common egg variables if not returned in relationships
        $startupStr = $eggAttributes['startup'] ?? '';
        if (!isset($environment['SERVER_JARFILE']) && str_contains($startupStr, 'SERVER_JARFILE')) {
            $environment['SERVER_JARFILE'] = $mergedSettings['SERVER_JARFILE'] ?? 'server.jar';
        }
        if (!isset($environment['BUILD_NUMBER']) && str_contains($startupStr, 'BUILD_NUMBER')) {
            $environment['BUILD_NUMBER'] = $mergedSettings['BUILD_NUMBER'] ?? 'latest';
        }
        if (!isset($environment['MINECRAFT_VERSION']) && str_contains($startupStr, 'MINECRAFT_VERSION')) {
            $environment['MINECRAFT_VERSION'] = $mergedSettings['MINECRAFT_VERSION'] ?? 'latest';
        }

        // 2. Resolve or create user on Pterodactyl
        $pteroUserId = self::resolveOrCreateUser($service, $client);

        // 3. Location handling
        if (isset($mergedSettings['location'])) {
            $mergedSettings['location_ids'] = [$mergedSettings['location']];
        }

        // 4. Allocation and Deployment Data
        $deploymentData = self::generateDeploymentData($mergedSettings, $environment, $client);

        // 5. Build Creation Payload
        $serverCreationData = [
            'external_id' => (string) $service->id,
            'name' => !empty($mergedSettings['servername']) ? $mergedSettings['servername'] : ($service->product->name . ' #' . $service->id),
            'user' => (int) $pteroUserId,
            'egg' => (int) $mergedSettings['egg_id'],
            'docker_image' => $mergedSettings['docker_image'] ?? $eggAttributes['docker_image'] ?? 'ghcr.io/pterodactyl/yolks:java_17',
            'startup' => $eggAttributes['startup'] ?? '',
            'environment' => $deploymentData['environment'],
            'skip_scripts' => (bool) ($mergedSettings['skip_scripts'] ?? false),
            'oom_disabled' => !((bool) ($mergedSettings['oom_killer'] ?? false)),
            'limits' => [
                'memory' => (int) ($mergedSettings['memory'] ?? 1024),
                'swap' => (int) ($mergedSettings['swap'] ?? 0),
                'disk' => (int) ($mergedSettings['disk'] ?? 5120),
                'io' => (int) ($mergedSettings['io'] ?? 500),
                'threads' => !empty($mergedSettings['cpu_pinning']) ? $mergedSettings['cpu_pinning'] : null,
                'cpu' => (int) ($mergedSettings['cpu'] ?? 100),
            ],
            'feature_limits' => [
                'databases' => (int) ($mergedSettings['databases'] ?? 0),
                'allocations' => (int) (($deploymentData['allocations_needed'] ?? 1) + ($mergedSettings['additional_allocations'] ?? 0)),
                'backups' => (int) ($mergedSettings['backups'] ?? 0),
            ],
            'start_on_completion' => (bool) ($mergedSettings['start_on_completion'] ?? false),
        ];

        if ($deploymentData['auto_deploy']) {
            $serverCreationData['deploy'] = [
                'locations' => (array) ($mergedSettings['location_ids'] ?? []),
                'dedicated_ip' => (bool) ($mergedSettings['dedicated_ip'] ?? false),
                'port_range' => (array) ($mergedSettings['port_range'] ?? []),
            ];
        } else {
            $serverCreationData['allocation'] = $deploymentData['allocation'];
        }

        // 6. Submit creation request to Pterodactyl
        $created = $client->appRequest('servers', 'POST', $serverCreationData);
        $server = ServerDetails::fromApiResponse($created, $client->getHost());

        // 7. Synchronize mappings on the Paymenter Service
        ServerResolver::syncProperties($service, $server);

        // 8. Generate and save initial credentials if not already stored
        if (!PasswordService::getStoredPassword($service)) {
            $initialPassword = PasswordService::generateSecurePassword();
            $encrypted = Crypt::encryptString($initialPassword);
            $service->properties()->updateOrCreate(['key' => 'ptero_password'], ['value' => $encrypted]);
            $service->properties()->updateOrCreate(['key' => 'epc_password'], ['value' => $encrypted]);
        }

        return [
            'server' => $server->id,
            'identifier' => $server->identifier,
            'uuid' => $server->uuid,
            'link' => $server->panelUrl,
        ];
    }

    /**
     * Resolve egg details supporting both v2 (direct) and v1 (nested) API paths.
     */
    protected static function resolveEggData(PterodactylClient $client, array $settings, string $version): array
    {
        $eggId = (int) ($settings['egg_id'] ?? 0);
        $nestId = $settings['nest_id'] ?? null;

        if (!$eggId) {
            throw new Exception('Product configuration is missing required egg_id.');
        }

        // Strategy 1: Fetch from eggs list with include=variables (standard in Panel 2.0 to obtain variable definitions)
        try {
            $allEggs = $client->appRequest('eggs?include=variables');
            if (!empty($allEggs['data']) && is_array($allEggs['data'])) {
                foreach ($allEggs['data'] as $eggItem) {
                    if ((int) ($eggItem['attributes']['id'] ?? 0) === $eggId) {
                        return $eggItem;
                    }
                }
            }
        } catch (Exception $e) {
            // Fall through
        }

        // Strategy 2: Direct single egg endpoint
        try {
            $data = $client->appRequest("eggs/{$eggId}?include=variables");
            if (isset($data['attributes'])) {
                return $data;
            }
        } catch (Exception $e) {
            // Fall through
        }

        // Strategy 3: Nested endpoint (Standard for Panel 1.x)
        if ($nestId) {
            $data = $client->appRequest("nests/{$nestId}/eggs/{$eggId}?include=variables");
            if (isset($data['attributes'])) {
                return $data;
            }
        }

        throw new Exception("Unable to fetch egg data for Egg ID '{$eggId}'. Please verify the egg ID and nest configuration.");
    }

    /**
     * Resolve existing user by email or create a new user.
     */
    protected static function resolveOrCreateUser(Service $service, PterodactylClient $client): int
    {
        $user = $service->user;
        $email = $user->email;

        // Check for existing user
        $lookup = $client->appRequest('users', 'GET', ['filter' => ['email' => $email]]);
        if (!empty($lookup['data'][0]['attributes']['id'])) {
            return (int) $lookup['data'][0]['attributes']['id'];
        }

        // Create new user
        $cleanUsername = preg_replace('/[^a-zA-Z0-9]/', '', strtolower(Str::transliterate($user->name ?: 'user')));
        if (empty($cleanUsername)) {
            $cleanUsername = 'user' . Str::random(6);
        } else {
            $cleanUsername = substr($cleanUsername, 0, 10) . '_' . Str::random(4);
        }

        $created = $client->appRequest('users', 'POST', [
            'email' => $email,
            'username' => $cleanUsername,
            'first_name' => !empty($user->first_name) ? $user->first_name : ($user->name ?: 'User'),
            'last_name' => !empty($user->last_name) ? $user->last_name : 'Client',
        ]);

        if (empty($created['attributes']['id'])) {
            throw new Exception('Failed to create Pterodactyl user account.');
        }

        return (int) $created['attributes']['id'];
    }

    /**
     * Generate allocation & deployment structures based on node and port settings.
     */
    protected static function generateDeploymentData(array $settings, array $environment, PterodactylClient $client): array
    {
        $hasPortArray = !empty($settings['port_array']) && is_string($settings['port_array']);

        if (!$hasPortArray) {
            if (!empty($settings['node'])) {
                $nodeAlloc = self::getSingleNodeAllocation($client, $settings);
                $environment['SERVER_PORT'] = $nodeAlloc['port'];

                return [
                    'auto_deploy' => false,
                    'environment' => $environment,
                    'allocations_needed' => 1,
                    'allocation' => [
                        'default' => $nodeAlloc['id'],
                        'additional' => [],
                    ],
                ];
            }

            return [
                'auto_deploy' => true,
                'environment' => $environment,
                'allocations_needed' => 1,
            ];
        }

        // Advanced port_array deployment
        return self::generatePortArrayDeployment($client, $settings, $environment);
    }

    protected static function getSingleNodeAllocation(PterodactylClient $client, array $settings): array
    {
        $nodesResponse = $client->appRequest('nodes/deployable', 'GET', [
            'memory' => $settings['memory'] ?? 1024,
            'disk' => $settings['disk'] ?? 5120,
            'location_ids' => $settings['location_ids'] ?? [],
            'include' => 'allocations',
        ]);

        $nodes = collect($nodesResponse['data'] ?? []);
        $targetNode = $nodes->firstWhere('attributes.id', $settings['node']);

        if (!$targetNode) {
            throw new Exception('The selected node is not suitable or available for deployment.');
        }

        $allocations = collect($targetNode['relationships']['allocations']['data'] ?? [])
            ->filter(fn ($port) => empty($port['attributes']['assigned']))
            ->map(fn ($port) => ['port' => $port['attributes']['port'], 'id' => $port['attributes']['id']]);

        if ($allocations->isEmpty()) {
            throw new Exception('No available allocations found on the selected node.');
        }

        return $allocations->first();
    }

    protected static function generatePortArrayDeployment(PterodactylClient $client, array $settings, array $environment): array
    {
        $portArray = json_decode($settings['port_array'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($portArray)) {
            throw new Exception('Invalid JSON in port_array configuration.');
        }

        $nodesResponse = $client->appRequest('nodes/deployable', 'GET', [
            'memory' => $settings['memory'] ?? 1024,
            'disk' => $settings['disk'] ?? 5120,
            'location_ids' => $settings['location_ids'] ?? [],
            'include' => 'allocations',
        ]);

        $nodes = collect($nodesResponse['data'] ?? []);
        $freeAllocationsNeeded = 0;
        foreach ($portArray as $val) {
            $freeAllocationsNeeded += is_array($val) ? count($val) : 1;
        }

        $availablePorts = collect();

        if (!empty($settings['node'])) {
            $node = $nodes->firstWhere('attributes.id', $settings['node']);
            if (!$node) {
                throw new Exception('Selected node is not deployable.');
            }
            $availablePorts = collect($node['relationships']['allocations']['data'] ?? [])
                ->filter(fn ($p) => empty($p['attributes']['assigned']))
                ->map(fn ($p) => ['port' => $p['attributes']['port'], 'id' => $p['attributes']['id']]);
        } else {
            foreach ($nodes as $node) {
                $ports = collect($node['relationships']['allocations']['data'] ?? $node['attributes']['relationships']['allocations']['data'] ?? [])
                    ->filter(fn ($p) => empty($p['attributes']['assigned']))
                    ->map(fn ($p) => ['port' => $p['attributes']['port'], 'id' => $p['attributes']['id']]);
                if ($ports->count() >= $freeAllocationsNeeded) {
                    $availablePorts = $ports;
                    break;
                }
            }
        }

        if ($availablePorts->count() < $freeAllocationsNeeded) {
            throw new Exception("Not enough available allocations for port_array. Required: {$freeAllocationsNeeded}, Available: {$availablePorts->count()}");
        }

        $assignedAllocations = [];
        foreach ($portArray as $key => $targetPort) {
            if (is_array($targetPort)) {
                foreach ($targetPort as $p) {
                    $match = $availablePorts->firstWhere('port', $p)
                        ?? $availablePorts->first(fn ($item) => $item['port'] > $p)
                        ?? $availablePorts->first();
                    $assignedAllocations[$key][] = $match;
                    $availablePorts = $availablePorts->reject(fn ($item) => $item['id'] == $match['id']);
                }
            } else {
                $match = $availablePorts->firstWhere('port', $targetPort)
                    ?? $availablePorts->first(fn ($item) => $item['port'] > $targetPort)
                    ?? $availablePorts->first();
                $assignedAllocations[$key] = $match;
                $availablePorts = $availablePorts->reject(fn ($item) => $item['id'] == $match['id']);
            }
        }

        $additionalIds = [];
        foreach ($assignedAllocations as $key => $alloc) {
            if ($key !== 'NONE' && isset($environment[$key])) {
                $environment[$key] = is_array($alloc) ? $alloc[0]['port'] : $alloc['port'];
            }
            if ($key !== 'SERVER_PORT') {
                if (is_array($alloc)) {
                    foreach ($alloc as $a) $additionalIds[] = $a['id'];
                } else {
                    $additionalIds[] = $alloc['id'];
                }
            }
        }

        $defaultAllocationId = $assignedAllocations['SERVER_PORT']['id'] ?? ($additionalIds[0] ?? null);

        return [
            'auto_deploy' => false,
            'allocations_needed' => $freeAllocationsNeeded,
            'environment' => $environment,
            'allocation' => [
                'default' => $defaultAllocationId,
                'additional' => $additionalIds,
            ],
        ];
    }
}
