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
        $serviceExternalId = (string) $service->id;

        // Strategy 1: External ID matching Paymenter Service ID (The canonical, unambiguous mapping)
        try {
            $response = $client->appRequest("servers/external/{$serviceExternalId}?include=allocations,node");
            if (isset($response['attributes']['id'])) {
                $server = ServerDetails::fromApiResponse($response, $client->getHost());
                self::syncProperties($service, $server);
                return $server;
            }
        } catch (Exception $e) {
            // Not found by external ID
        }

        // Strategy 2: Stored numeric server ID (with verification)
        $storedServerId = $properties['ptero_server_id'] ?? $properties['server_id'] ?? null;
        if ($storedServerId && is_numeric($storedServerId)) {
            try {
                $response = $client->appRequest("servers/{$storedServerId}?include=allocations,node");
                if (isset($response['attributes']['id'])) {
                    $remoteExternalId = (string) ($response['attributes']['external_id'] ?? '');
                    // Only match if remote external_id is empty or matches this service ID
                    if (empty($remoteExternalId) || $remoteExternalId === $serviceExternalId) {
                        $server = ServerDetails::fromApiResponse($response, $client->getHost());
                        self::syncProperties($service, $server);
                        return $server;
                    } else {
                        // Stale property pointing to a completely different server! Purge it.
                        $service->properties()->whereIn('key', ['ptero_server_id', 'server_id', 'ptero_identifier', 'ptero_server_uuid'])->delete();
                    }
                }
            } catch (Exception $e) {
                // If stored ID not found on panel, fall through
            }
        }

        // Strategy 3: Stored short identifier or UUID (with exact matching)
        $storedIdentifier = $properties['ptero_identifier'] ?? $properties['identifier'] ?? null;
        $storedUuid = $properties['ptero_server_uuid'] ?? $properties['server_uuid'] ?? null;
        $identifierToSearch = $storedIdentifier ?: $storedUuid;

        if ($identifierToSearch) {
            try {
                $response = $client->appRequest("servers?include=allocations,node");
                $serversList = $response['data'] ?? [];
                foreach ($serversList as $item) {
                    $attr = $item['attributes'] ?? [];
                    if (($attr['identifier'] ?? '') === $identifierToSearch || ($attr['uuid'] ?? '') === $identifierToSearch) {
                        $remoteExternalId = (string) ($attr['external_id'] ?? '');
                        if (empty($remoteExternalId) || $remoteExternalId === $serviceExternalId) {
                            $server = ServerDetails::fromApiResponse($item, $client->getHost());
                            self::syncProperties($service, $server);
                            return $server;
                        }
                    }
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
