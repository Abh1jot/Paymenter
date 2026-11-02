<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Services;

use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Servers\PterodactylV2\DTO\ServerDetails;

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
        $server = ServerResolver::resolve($service, $client, failIfNotFound: false);
        if (!$server) {
            return false;
        }

        try {
            $client->appRequest("servers/{$server->id}/suspend", 'POST');
        } catch (Exception $e) {
            $msg = strtolower($e->getMessage());
            if (!str_contains($msg, 'already suspended') && !str_contains($msg, 'is suspended')) {
                throw $e;
            }
        }

        // Sync suspension state with panel extension if installed
        try {
            $client->appRequest('extensions/server-suspension/action', 'POST', [
                'server_id' => (int) $server->id,
                'action' => 'suspend',
            ]);
        } catch (\Throwable $e) {
            // Ignore if extension is not installed
        }

        return true;
    }

    /**
     * Unsuspend server on Pterodactyl.
     *
     * @throws Exception
     */
    public static function unsuspend(Service $service, PterodactylClient $client): bool
    {
        $server = ServerResolver::resolve($service, $client, failIfNotFound: false);
        if (!$server) {
            Log::warning("PterodactylV2: Could not resolve server for Service #{$service->id} during unsuspend.");
            return false;
        }

        // 1. Unsuspend on Pterodactyl core Application API
        try {
            $client->appRequest("servers/{$server->id}/unsuspend", 'POST');
        } catch (Exception $e) {
            $msg = strtolower($e->getMessage());
            if (!str_contains($msg, 'not suspended') 
                && !str_contains($msg, 'already active') 
                && !str_contains($msg, 'cannot unsuspend')
                && !str_contains($msg, 'is not suspended')
                && !str_contains($msg, 'already unsuspended')) {
                Log::warning("PterodactylV2 unsuspend API call for server #{$server->id} returned: " . $e->getMessage());
            }
        }

        // 2. Synchronize suspension schedule with Pterodactyl extensions (e.g. pteroext-server-suspension / autosuspend)
        // This ensures the panel's automated 1-minute cron does not immediately re-suspend the server.
        self::syncSuspensionSchedule($service, $server, $client);

        return true;
    }

    /**
     * Synchronize extended date and active state with Pterodactyl suspension extensions.
     */
    public static function syncSuspensionSchedule(Service $service, ServerDetails $server, PterodactylClient $client): void
    {
        $expiryDate = $service->expires_at;
        $dateStr = ($expiryDate && $expiryDate->isFuture()) ? $expiryDate->format('Y-m-d H:i:s') : null;

        // 1. Call pteroext-server-suspension extension if available on panel
        try {
            $client->appRequest('extensions/server-suspension/action', 'POST', [
                'server_id' => (int) $server->id,
                'action' => 'unsuspend',
            ]);
        } catch (\Throwable $e) {}

        if ($dateStr) {
            try {
                $client->appRequest('extensions/server-suspension/schedule', 'POST', [
                    'server_id' => (int) $server->id,
                    'suspension_date' => $dateStr,
                    'status' => 'active',
                    'notify_user' => true,
                ]);
            } catch (\Throwable $e) {}
        }

        // 2. Call legacy autosuspend blueprint extension if available on panel
        if ($dateStr) {
            try {
                $client->appRequest("extensions/autosuspend/{$server->id}", 'PATCH', [
                    'suspension_date' => $dateStr,
                ]);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Terminate (delete) server on Pterodactyl with strict safety checks.
     *
     * @throws Exception
     */
    public static function terminate(Service $service, PterodactylClient $client): bool
    {
        $server = ServerResolver::resolve($service, $client, failIfNotFound: false);

        if (!$server) {
            // Already deleted on Pterodactyl; safely purge properties and complete
            $service->properties()->whereIn('key', ['ptero_server_id', 'ptero_server_uuid', 'ptero_identifier', 'server_id', 'ptero_password', 'epc_password'])->delete();
            return true;
        }

        // Security check: Validate that the external_id matches the service ID
        if ($server->externalId !== null && (string) $server->externalId !== (string) $service->id) {
            throw new Exception("Safety mismatch: Pterodactyl server #{$server->id} has external_id '{$server->externalId}' which does not match Service ID '{$service->id}'. Aborting termination.");
        }

        try {
            $client->appRequest("servers/{$server->id}", 'DELETE');
        } catch (Exception $e) {
            if (!str_contains($e->getMessage(), '404') && !str_contains(strtolower($e->getMessage()), 'not found')) {
                throw $e;
            }
        }

        // Clean up cached properties
        $service->properties()->whereIn('key', ['ptero_server_id', 'ptero_server_uuid', 'ptero_identifier', 'server_id', 'ptero_password', 'epc_password'])->delete();

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
        $defaultAllocationId = $server->allocationId ?? $server->raw['allocation'] ?? null;
        $updateBuildData = [
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

        if ($defaultAllocationId && is_numeric($defaultAllocationId)) {
            $updateBuildData['allocation'] = (int) $defaultAllocationId;
        }

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
