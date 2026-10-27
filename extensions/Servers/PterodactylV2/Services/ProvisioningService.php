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

        // 1. Fetch Egg and resolve all required environment variables
        $eggData = self::resolveEggData($client, $mergedSettings, $version);
        $eggAttributes = $eggData['attributes'] ?? [];

        $environment = self::resolveEnvironment($eggAttributes, $mergedSettings);

        // 2. Resolve or create user on Pterodactyl
        $pteroUserId = self::resolveOrCreateUser($service, $client);

        // 3. Location handling
        if (isset($mergedSettings['location'])) {
            $mergedSettings['location_ids'] = [$mergedSettings['location']];
        }

        // 4. Allocation and Deployment Data
        $deploymentData = self::generateDeploymentData($mergedSettings, $environment, $client);

        // 5. Build Creation Payload
        $dockerImage = $mergedSettings['docker_image'] ?? $mergedSettings['image'] ?? null;
        if (empty($dockerImage)) {
            if (!empty($eggAttributes['docker_image'])) {
                $dockerImage = $eggAttributes['docker_image'];
            } elseif (!empty($eggAttributes['docker_images']) && is_array($eggAttributes['docker_images'])) {
                $dockerImage = reset($eggAttributes['docker_images']);
            } else {
                $dockerImage = 'ghcr.io/pterodactyl/yolks:java_17';
            }
        }

        $startup = !empty($mergedSettings['startup']) ? (string) $mergedSettings['startup'] : ($eggAttributes['startup'] ?? '');
        $rawName = $mergedSettings['servername'] ?? $mergedSettings['server_name'] ?? $mergedSettings['hostname'] ?? $mergedSettings['name'] ?? null;
        $serverName = $rawName ? substr(trim((string) $rawName), 0, 191) : ($service->product->name . ' #' . $service->id);

        $allocationsNeeded = (int) ($deploymentData['allocations_needed'] ?? 1);
        $additionalAllocations = (int) ($mergedSettings['additional_allocations'] ?? $mergedSettings['allocations'] ?? 0);

        $serverCreationData = [
            'external_id' => (string) $service->id,
            'name' => $serverName,
            'user' => (int) $pteroUserId,
            'egg' => (int) $mergedSettings['egg_id'],
            'docker_image' => $dockerImage,
            'startup' => $startup,
            'environment' => $deploymentData['environment'],
            'skip_scripts' => (bool) ($mergedSettings['skip_scripts'] ?? false),
            'oom_disabled' => !((bool) ($mergedSettings['oom_killer'] ?? false)),
            'limits' => [
                'memory' => (int) ($mergedSettings['memory'] ?? 1024),
                'swap' => (int) ($mergedSettings['swap'] ?? 0),
                'disk' => (int) ($mergedSettings['disk'] ?? 5120),
                'io' => max(10, min(1000, (int) ($mergedSettings['io'] ?? 500))),
                'threads' => !empty($mergedSettings['cpu_pinning']) ? (string) $mergedSettings['cpu_pinning'] : null,
                'cpu' => (int) ($mergedSettings['cpu'] ?? 100),
            ],
            'feature_limits' => [
                'databases' => max(0, (int) ($mergedSettings['databases'] ?? 0)),
                'allocations' => max(1, $allocationsNeeded + $additionalAllocations),
                'backups' => max(0, (int) ($mergedSettings['backups'] ?? 0)),
            ],
            'start_on_completion' => (bool) ($mergedSettings['start_on_completion'] ?? false),
        ];

        if ($deploymentData['auto_deploy']) {
            $locationIds = array_values(array_filter(array_map('intval', (array) ($mergedSettings['location_ids'] ?? [])), fn ($id) => $id > 0));

            // Auto-fallback: if location_ids was omitted, query locations to satisfy Pterodactyl validator
            if (empty($locationIds)) {
                try {
                    $locationsResponse = $client->appRequest('locations');
                    foreach ($locationsResponse['data'] ?? [] as $loc) {
                        $lid = (int) ($loc['attributes']['id'] ?? 0);
                        if ($lid > 0) {
                            $locationIds[] = $lid;
                        }
                    }
                } catch (Exception $e) {
                    // Ignore
                }
            }

            $portRange = $mergedSettings['port_range'] ?? [];
            if (is_string($portRange)) {
                $portRange = array_values(array_filter(array_map('trim', explode(',', $portRange))));
            }

            $serverCreationData['deploy'] = [
                'locations' => $locationIds,
                'dedicated_ip' => (bool) ($mergedSettings['dedicated_ip'] ?? false),
                'port_range' => (array) $portRange,
            ];
        } else {
            $serverCreationData['allocation'] = $deploymentData['allocation'];
        }

        // 6. Submit creation request to Pterodactyl with conflict recovery
        try {
            $created = $client->appRequest('servers', 'POST', $serverCreationData);
            $server = ServerDetails::fromApiResponse($created, $client->getHost());
        } catch (Exception $e) {
            $msg = strtolower($e->getMessage());
            // If external_id already exists on Pterodactyl (e.g. from network timeout on earlier attempt), re-resolve existing server
            if (str_contains($msg, 'external id has already been taken') || str_contains($msg, 'external_id')) {
                $existing = ServerResolver::resolve($service, $client, failIfNotFound: false);
                if ($existing) {
                    $server = $existing;
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

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
     * Resolve environment variables for the server creation payload.
     */
    protected static function resolveEnvironment(array $eggAttributes, array $mergedSettings): array
    {
        $environment = [];
        $variables = $eggAttributes['relationships']['variables']['data'] ?? [];

        foreach ($variables as $variable) {
            $vAttr = $variable['attributes'] ?? [];
            $envVar = $vAttr['env_variable'] ?? '';
            $varName = $vAttr['name'] ?? '';
            $rules = (string) ($vAttr['rules'] ?? '');
            $defaultValue = $vAttr['default_value'] ?? '';

            if (empty($envVar)) {
                continue;
            }

            // Case-insensitive, symbol-agnostic match from customer or product settings
            $providedValue = self::findSettingValue($mergedSettings, $envVar, $varName);

            if ($providedValue !== null && $providedValue !== '') {
                $environment[$envVar] = (string) $providedValue;
            } elseif ($defaultValue !== null && $defaultValue !== '') {
                $environment[$envVar] = (string) $defaultValue;
            } else {
                // If value is empty or not provided, check if required or provide safe default
                $fallback = self::resolveFallbackVariableValue($envVar, $rules);
                $environment[$envVar] = $fallback !== null ? $fallback : '';
            }
        }

        // Script & startup command scanning fallback if variables relationship was empty or missing key variables
        $scriptStr = ($eggAttributes['startup'] ?? '') . ' ' . json_encode($eggAttributes['script'] ?? []);
        $eggName = $eggAttributes['name'] ?? '';

        $criticalVars = [
            'SERVER_JARFILE' => ['server.jar', ['Server Jar File', 'server_jar_file', 'server_jarfile', 'jarfile']],
            'BUILD_NUMBER' => ['latest', ['Build Number', 'build_number', 'build']],
            'MINECRAFT_VERSION' => ['latest', ['Minecraft Version', 'minecraft_version', 'mc_version']],
            'VERSION' => ['latest', ['Version', 'version']],
            'VANILLA_VERSION' => ['latest', ['Vanilla Version', 'vanilla_version']],
            'BUNGEE_VERSION' => ['latest', ['Bungee Version', 'bungee_version']],
            'JS_FILE' => ['index.js', ['Main File', 'js_file', 'main_file', 'bot_js_file']],
            'BOT_JS_FILE' => ['index.js', ['Bot JS File', 'bot_js_file', 'main_file']],
        ];

        foreach ($criticalVars as $cVar => [$defaultFallback, $aliases]) {
            if (!isset($environment[$cVar])) {
                $mentionedInStartup = str_contains($scriptStr, $cVar) || str_contains($scriptStr, '{{' . $cVar . '}}');
                $isMinecraftEgg = str_contains($eggName, 'Paper') || str_contains($eggName, 'Minecraft') || str_contains($eggName, 'Purpur') || str_contains($eggName, 'Forge') || str_contains($eggName, 'Fabric') || str_contains($eggName, 'Spigot');

                if ($mentionedInStartup || ($isMinecraftEgg && in_array($cVar, ['SERVER_JARFILE', 'BUILD_NUMBER', 'MINECRAFT_VERSION']))) {
                    $found = self::findSettingValue($mergedSettings, $cVar, $aliases[0] ?? '');
                    $environment[$cVar] = ($found !== null && $found !== '') ? (string) $found : $defaultFallback;
                }
            }
        }

        return $environment;
    }

    /**
     * Case-insensitive, symbol-agnostic setting search across product settings and configurable options.
     */
    protected static function findSettingValue(array $settings, string $envVar, string $varName = ''): ?string
    {
        // 1. Direct key match
        if (isset($settings[$envVar]) && $settings[$envVar] !== '') {
            return (string) $settings[$envVar];
        }
        if ($varName && isset($settings[$varName]) && $settings[$varName] !== '') {
            return (string) $settings[$varName];
        }

        // 2. Normalized matching
        $normalizedEnv = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $envVar));
        $normalizedName = $varName ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $varName)) : '';

        foreach ($settings as $key => $val) {
            if ($val === null || $val === '') {
                continue;
            }
            $cleanKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $key));
            if ($cleanKey === $normalizedEnv || ($normalizedName && $cleanKey === $normalizedName)) {
                return (string) $val;
            }
        }

        return null;
    }

    /**
     * Provide safe defaults for variables when empty, preventing Pterodactyl 422 required validation errors.
     */
    protected static function resolveFallbackVariableValue(string $envVar, string $rules): ?string
    {
        $isRequired = str_contains($rules, 'required');

        return match (strtoupper($envVar)) {
            'SERVER_JARFILE' => 'server.jar',
            'BUILD_NUMBER' => 'latest',
            'MINECRAFT_VERSION', 'VERSION', 'VANILLA_VERSION', 'BUNGEE_VERSION' => 'latest',
            'JS_FILE', 'BOT_JS_FILE', 'MAIN_FILE' => 'index.js',
            'PY_FILE' => 'main.py',
            'AUTO_UPDATE' => '0',
            default => $isRequired
                ? (str_contains($rules, 'numeric') || str_contains($rules, 'integer')
                    ? '0'
                    : (str_contains($rules, 'boolean') ? '0' : 'default'))
                : null,
        };
    }

    /**
     * Resolve egg details supporting v2 (direct), v1 (nested), and nest-discovery fallbacks.
     */
    protected static function resolveEggData(PterodactylClient $client, array $settings, string $version): array
    {
        $eggId = (int) ($settings['egg_id'] ?? 0);
        $nestId = $settings['nest_id'] ?? null;

        if (!$eggId) {
            throw new Exception('Product configuration is missing required egg_id.');
        }

        // Strategy 1: Direct single egg endpoint with variables included (Pterodactyl 2.0 / API direct)
        try {
            $data = $client->appRequest("eggs/{$eggId}?include=variables");
            if (isset($data['attributes'])) {
                return $data;
            }
        } catch (Exception $e) {
            // Fall through
        }

        // Strategy 2: Direct egg list search (Panel 2.0)
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

        // Strategy 3: Nested endpoint (Standard for Panel 1.x when nest_id is provided)
        if ($nestId) {
            try {
                $data = $client->appRequest("nests/{$nestId}/eggs/{$eggId}?include=variables");
                if (isset($data['attributes'])) {
                    return $data;
                }
            } catch (Exception $e) {
                // Fall through
            }
        }

        // Strategy 4: Fallback nest discovery (Queries panel nests to find which nest contains this egg)
        try {
            $nestsResponse = $client->appRequest('nests');
            $nestsList = $nestsResponse['data'] ?? [];
            foreach ($nestsList as $nest) {
                $nid = $nest['attributes']['id'] ?? null;
                if (!$nid) {
                    continue;
                }
                try {
                    $egg = $client->appRequest("nests/{$nid}/eggs/{$eggId}?include=variables");
                    if (isset($egg['attributes'])) {
                        return $egg;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        } catch (Exception $e) {
            // Fall through
        }

        throw new Exception("Unable to fetch egg data for Egg ID '{$eggId}'. Please verify the egg ID and nest configuration on your Pterodactyl panel.");
    }

    /**
     * Resolve existing user by email or create a new user.
     */
    protected static function resolveOrCreateUser(Service $service, PterodactylClient $client): int
    {
        $user = $service->user;
        $email = trim((string) $user->email);

        // 1. Check for existing user via filter query
        try {
            $lookup = $client->appRequest('users', 'GET', ['filter' => ['email' => $email]]);
            if (!empty($lookup['data']) && is_array($lookup['data'])) {
                foreach ($lookup['data'] as $item) {
                    if (strcasecmp($item['attributes']['email'] ?? '', $email) === 0) {
                        return (int) $item['attributes']['id'];
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore filter errors and proceed to creation or fallback search
        }

        // 2. Format user credentials
        $cleanUsername = preg_replace('/[^a-zA-Z0-9]/', '', strtolower(Str::transliterate($user->name ?: 'user')));
        if (empty($cleanUsername)) {
            $baseUsername = 'user' . Str::random(4);
        } else {
            $baseUsername = substr($cleanUsername, 0, 10);
        }

        $fullName = trim((string) ($user->name ?: ($user->first_name . ' ' . $user->last_name)));
        $nameParts = preg_split('/\s+/', $fullName, 2);
        $firstName = !empty($user->first_name) ? trim($user->first_name) : ($nameParts[0] ?? 'User');
        $lastName = !empty($user->last_name) ? trim($user->last_name) : ($nameParts[1] ?? 'Client');
        if (empty($firstName)) {
            $firstName = 'User';
        }
        if (empty($lastName)) {
            $lastName = 'Client';
        }

        // 3. Attempt creation with retry on username collision
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $username = $baseUsername . '_' . Str::random(4 + $attempt * 2);

            try {
                $created = $client->appRequest('users', 'POST', [
                    'email' => $email,
                    'username' => $username,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);

                if (!empty($created['attributes']['id'])) {
                    return (int) $created['attributes']['id'];
                }
            } catch (Exception $e) {
                $msg = strtolower($e->getMessage());

                // If email already taken, find and return existing user
                if (str_contains($msg, 'email has already been taken') || str_contains($msg, 'email_taken')) {
                    $existingId = self::findUserByEmail($client, $email);
                    if ($existingId) {
                        return $existingId;
                    }
                    throw new Exception("Pterodactyl user account with email '{$email}' already exists, but could not be retrieved: " . $e->getMessage());
                }

                // If username is taken, loop and try again with new randomized suffix
                if (str_contains($msg, 'username has already been taken') && $attempt < $maxAttempts) {
                    continue;
                }

                throw $e;
            }
        }

        throw new Exception("Failed to create or resolve Pterodactyl user account for '{$email}'.");
    }

    /**
     * Fallback lookup iterating user pages to locate an account with exact email match.
     */
    protected static function findUserByEmail(PterodactylClient $client, string $email): ?int
    {
        // 1. Try search query
        try {
            $search = $client->appRequest('users', 'GET', ['search' => $email]);
            if (!empty($search['data']) && is_array($search['data'])) {
                foreach ($search['data'] as $item) {
                    if (strcasecmp($item['attributes']['email'] ?? '', $email) === 0) {
                        return (int) $item['attributes']['id'];
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore
        }

        // 2. Paginate users
        for ($page = 1; $page <= 5; $page++) {
            try {
                $res = $client->appRequest('users', 'GET', ['page' => $page]);
                if (empty($res['data']) || !is_array($res['data'])) {
                    break;
                }
                foreach ($res['data'] as $item) {
                    if (strcasecmp($item['attributes']['email'] ?? '', $email) === 0) {
                        return (int) $item['attributes']['id'];
                    }
                }
                $pagination = $res['meta']['pagination'] ?? [];
                if ($page >= ($pagination['total_pages'] ?? 1)) {
                    break;
                }
            } catch (Exception $e) {
                break;
            }
        }

        return null;
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
        $params = [
            'memory' => $settings['memory'] ?? 1024,
            'disk' => $settings['disk'] ?? 5120,
            'include' => 'allocations',
        ];
        if (!empty($settings['location_ids'])) {
            $params['location_ids'] = (array) $settings['location_ids'];
        }

        $nodesResponse = $client->appRequest('nodes/deployable', 'GET', $params);

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

        $params = [
            'memory' => $settings['memory'] ?? 1024,
            'disk' => $settings['disk'] ?? 5120,
            'include' => 'allocations',
        ];
        if (!empty($settings['location_ids'])) {
            $params['location_ids'] = (array) $settings['location_ids'];
        }

        $nodesResponse = $client->appRequest('nodes/deployable', 'GET', $params);

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
                    $intP = (int) $p;
                    $match = $availablePorts->first(fn ($item) => (int) $item['port'] === $intP)
                        ?? $availablePorts->first(fn ($item) => (int) $item['port'] > $intP)
                        ?? $availablePorts->first();
                    $assignedAllocations[$key][] = $match;
                    $availablePorts = $availablePorts->reject(fn ($item) => $item['id'] == $match['id']);
                }
            } else {
                $intP = (int) $targetPort;
                $match = $availablePorts->first(fn ($item) => (int) $item['port'] === $intP)
                    ?? $availablePorts->first(fn ($item) => (int) $item['port'] > $intP)
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
                    foreach ($alloc as $a) {
                        $additionalIds[] = $a['id'];
                    }
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
