<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Services;

use App\Models\Service;
use Exception;
use Paymenter\Extensions\Servers\PterodactylV2\DTO\ResourceUsage;
use Paymenter\Extensions\Servers\PterodactylV2\DTO\ServerDetails;

/**
 * Service to execute container power actions and fetch live performance telemetry
 * via the Pterodactyl Client API (/api/client/servers/{uuid}/...).
 */
class PowerService
{
    /**
     * Supported power signals.
     */
    public const VALID_SIGNALS = ['start', 'stop', 'restart', 'kill'];

    /**
     * Dispatch a power action signal to a server.
     *
     * @throws Exception
     */
    public static function sendPowerAction(Service $service, string $signal, PterodactylClient $client): bool
    {
        $signal = strtolower(trim($signal));
        if (!in_array($signal, self::VALID_SIGNALS, true)) {
            throw new Exception("Invalid power signal '{$signal}'. Supported signals: " . implode(', ', self::VALID_SIGNALS));
        }

        if (!$client->hasClientKey()) {
            throw new Exception('Client API Key is not configured in Server settings. Direct power actions require an administrative Client API key.');
        }

        $server = ServerResolver::resolve($service, $client, failIfNotFound: true);

        // Pterodactyl Client API accepts server UUID or short identifier
        // In v2, the UUID is preferred
        $targetId = !empty($server->uuid) ? $server->uuid : $server->identifier;

        $client->clientRequest("servers/{$targetId}/power", 'POST', [
            'signal' => $signal,
        ]);

        return true;
    }

    /**
     * Fetch real-time container resource utilization.
     *
     * @throws Exception
     */
    public static function getResourceUsage(Service $service, ServerDetails $server, PterodactylClient $client): ?ResourceUsage
    {
        if (!$client->hasClientKey()) {
            return null;
        }

        $targetId = !empty($server->uuid) ? $server->uuid : $server->identifier;

        try {
            $response = $client->clientRequest("servers/{$targetId}/resources", 'GET');
            if (isset($response['attributes'])) {
                return ResourceUsage::fromClientApiResponse(
                    $response,
                    memoryLimitMb: $server->memoryLimit,
                    cpuLimit: $server->cpuLimit,
                    diskLimitMb: $server->diskLimit
                );
            }
        } catch (Exception $e) {
            // If the server is offline or installing, wings might return error or 404/500
        }

        return new ResourceUsage(
            currentState: $server->isSuspended ? 'suspended' : ($server->isInstalling ? 'installing' : 'offline'),
            isSuspended: $server->isSuspended,
            memoryLimitMb: (float) $server->memoryLimit,
            cpuLimit: (float) $server->cpuLimit,
            diskLimitMb: (float) $server->diskLimit
        );
    }
}
