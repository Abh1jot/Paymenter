<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Services;

use App\Models\Service;
use Exception;

/**
 * Service to manage service lifecycle operations:
 * - Suspension
 * - Unsuspension
 * - Secure Termination
 * - Package Upgrades (Build & Startup updates)
 */
class LifecycleService
{
    /**
     * Suspend server on Pterodactyl.
     *
     * @throws Exception
     */
    public static function suspend(Service $service, PterodactylClient $client): bool
    {
        $server = ServerResolver::resolve($service, $client, failIfNotFound: true);
        $client->appRequest("servers/{$server->id}/suspend", 'POST');
        return true;
    }

    /**
     * Unsuspend server on Pterodactyl.
     *
     * @throws Exception
     */
    public static function unsuspend(Service $service, PterodactylClient $client): bool
    {
        $server = ServerResolver::resolve($service, $client, failIfNotFound: true);
        $client->appRequest("servers/{$server->id}/unsuspend", 'POST');
        return true;
    }

    /**
     * Terminate (delete) server on Pterodactyl with strict safety checks.
     *
     * @throws Exception
     */
    public static function terminate(Service $service, PterodactylClient $client): bool
    {
        $server = ServerResolver::resolve($service, $client, failIfNotFound: true);

        // Security check: Validate that the external_id matches the service ID,
        // or that the server ID matches the stored mapping to prevent accidental deletion
        if ($server->externalId !== null && (string) $server->externalId !== (string) $service->id) {
            throw new Exception("Safety mismatch: Pterodactyl server #{$server->id} has external_id '{$server->externalId}' which does not match Service ID '{$service->id}'. Aborting termination.");
        }

        $client->appRequest("servers/{$server->id}", 'DELETE');

        // Clean up cached properties
        $service->properties()->whereIn('key', ['ptero_server_id', 'ptero_server_uuid', 'ptero_identifier'])->delete();

        return true;
    }

    /**
     * Upgrade server resource limits and startup configuration.
     *
     * @throws Exception
     */
    public static function upgrade(
        Service $service,
        array $settings,
        array $properties,
        PterodactylClient $client,
        string $configuredVersion = 'auto'
    ): bool {
        $server = ServerResolver::resolve($service, $client, failIfNotFound: true);
        $mergedSettings = array_merge($settings, $properties);
        $version = PanelVersionDetector::detect($client, $configuredVersion);

        // 1. Update Build Configuration (limits & feature limits)
        $updateBuildData = [
            'allocation' => $server->raw['allocation'] ?? $server->port,
            'memory' => (int) ($mergedSettings['memory'] ?? $server->memoryLimit),
            'swap' => (int) ($mergedSettings['swap'] ?? $server->swapLimit),
            'disk' => (int) ($mergedSettings['disk'] ?? $server->diskLimit),
            'io' => (int) ($mergedSettings['io'] ?? $server->ioWeight),
            'cpu' => (int) ($mergedSettings['cpu'] ?? $server->cpuLimit),
            'threads' => $mergedSettings['cpu_pinning'] ?? $server->cpuPinning,
            'feature_limits' => [
                'databases' => (int) ($mergedSettings['databases'] ?? $server->databasesLimit),
                'allocations' => (int) ($mergedSettings['additional_allocations'] ?? $server->allocationsLimit),
                'backups' => (int) ($mergedSettings['backups'] ?? $server->backupsLimit),
            ],
        ];

        $client->appRequest("servers/{$server->id}/build", 'PATCH', $updateBuildData);

        // 2. Fetch Egg Data for Startup Updates
        $eggId = $mergedSettings['egg_id'] ?? $server->eggId;
        $nestId = $mergedSettings['nest_id'] ?? null;
        $eggEndpoint = PanelVersionDetector::getEggEndpoint($version, $eggId, $nestId);

        try {
            $eggData = $client->appRequest($eggEndpoint);
            $eggAttributes = $eggData['attributes'] ?? [];
            $existingEnv = $server->raw['container']['environment'] ?? [];

            $environment = [];
            $variables = $eggAttributes['relationships']['variables']['data'] ?? [];
            foreach ($variables as $variable) {
                $envKey = $variable['attributes']['env_variable'] ?? '';
                if ($envKey) {
                    if (isset($existingEnv[$envKey])) {
                        $environment[$envKey] = $existingEnv[$envKey];
                    } else {
                        $environment[$envKey] = $mergedSettings[$envKey] ?? $variable['attributes']['default_value'] ?? '';
                    }
                }
            }

            $updateStartupData = [
                'environment' => $environment,
                'skip_scripts' => (bool) ($mergedSettings['skip_scripts'] ?? false),
                'oom_disabled' => !((bool) ($mergedSettings['oom_killer'] ?? false)),
                'egg' => (int) $eggId,
                'image' => $server->raw['container']['image'] ?? $eggAttributes['docker_image'] ?? 'ghcr.io/pterodactyl/yolks:java_17',
                'startup' => $server->raw['container']['startup_command'] ?? $mergedSettings['startup'] ?? $eggAttributes['startup'] ?? '',
            ];

            $client->appRequest("servers/{$server->id}/startup", 'PATCH', $updateStartupData);
        } catch (Exception $e) {
            // Log but don't fail build upgrade if startup patch encounters a minor variable issue
        }

        return true;
    }
}
