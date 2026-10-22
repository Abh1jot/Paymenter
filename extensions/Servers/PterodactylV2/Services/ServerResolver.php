<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Services;

use App\Models\Service;
use Exception;
use Paymenter\Extensions\Servers\PterodactylV2\DTO\ServerDetails;

/**
 * Service to reliably resolve, verify, and cache the relationship
 * between a Paymenter Service and its associated Pterodactyl server.
 *
 * Implements strict backward compatibility for services created under
 * EasyPtroClient Gold, stock Pterodactyl, or custom migrations.
 */
class ServerResolver
{
    /**
     * Resolve a server from Pterodactyl for the given Paymenter Service.
     *
     * @param Service $service
     * @param PterodactylClient $client
     * @param bool $failIfNotFound
     * @return ServerDetails|null
     * @throws Exception
     */
    public static function resolve(Service $service, PterodactylClient $client, bool $failIfNotFound = true): ?ServerDetails
    {
        $properties = $service->properties->pluck('value', 'key')->toArray();

        // Strategy 1: Stored numeric server ID
        $storedServerId = $properties['ptero_server_id'] ?? $properties['server_id'] ?? null;
        if ($storedServerId && is_numeric($storedServerId)) {
            try {
                $response = $client->appRequest("servers/{$storedServerId}?include=allocations,node");
                if (isset($response['attributes']['id'])) {
                    $server = ServerDetails::fromApiResponse($response, $client->getHost());
                    self::syncProperties($service, $server);
                    return $server;
                }
            } catch (Exception $e) {
                // If the stored ID failed, fall through to external_id check
            }
        }

        // Strategy 2: External ID matching Paymenter Service ID (Used by EasyPtroClient and stock Pterodactyl)
        $externalId = (string) $service->id;
        try {
            $response = $client->appRequest("servers/external/{$externalId}?include=allocations,node");
            if (isset($response['attributes']['id'])) {
                $server = ServerDetails::fromApiResponse($response, $client->getHost());
                self::syncProperties($service, $server);
                return $server;
            }
        } catch (Exception $e) {
            // Not found by external ID
        }

        // Strategy 3: Stored UUID or identifier
        $storedIdentifier = $properties['ptero_identifier'] ?? $properties['identifier'] ?? null;
        $storedUuid = $properties['ptero_server_uuid'] ?? $properties['server_uuid'] ?? null;
        $identifierToSearch = $storedIdentifier ?: $storedUuid;

        if ($identifierToSearch) {
            try {
                // Search via server listing filter or external lookup
                $response = $client->appRequest("servers?filter[identifier]={$identifierToSearch}&include=allocations,node");
                if (!empty($response['data'][0]['attributes']['id'])) {
                    $server = ServerDetails::fromApiResponse($response['data'][0], $client->getHost());
                    self::syncProperties($service, $server);
                    return $server;
                }
            } catch (Exception $e) {
                // Ignore
            }
        }

        if ($failIfNotFound) {
            throw new Exception("Pterodactyl server could not be resolved for Service #{$service->id}. Please verify that the server exists on the panel and has external_id '{$service->id}'.");
        }

        return null;
    }

    /**
     * Safely synchronize and cache resolved server identifiers on the Paymenter Service.
     */
    public static function syncProperties(Service $service, ServerDetails $server): void
    {
        // Cache numeric ID
        $service->properties()->updateOrCreate(
            ['key' => 'ptero_server_id'],
            ['value' => (string) $server->id]
        );

        // Cache UUID
        $service->properties()->updateOrCreate(
            ['key' => 'ptero_server_uuid'],
            ['value' => $server->uuid]
        );

        // Cache short identifier
        $service->properties()->updateOrCreate(
            ['key' => 'ptero_identifier'],
            ['value' => $server->identifier]
        );

        // Also maintain legacy server_id for backward compatibility
        $service->properties()->updateOrCreate(
            ['key' => 'server_id'],
            ['value' => (string) $server->id]
        );
    }

    /**
     * Check if a service has a known, valid association without making remote network calls if already cached.
     */
    public static function hasStoredMapping(Service $service): bool
    {
        $properties = $service->properties->pluck('value', 'key')->toArray();

        return !empty($properties['ptero_server_id'])
            || !empty($properties['server_id'])
            || !empty($properties['ptero_server_uuid'])
            || !empty($properties['ptero_identifier']);
    }
}
