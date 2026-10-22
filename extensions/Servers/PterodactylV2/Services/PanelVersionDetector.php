<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Services;

use Exception;

/**
 * Intelligent version detection between Pterodactyl Panel 1.x and Panel 2.x.
 *
 * Pterodactyl 2.0 replaces nests with tags, removing the /api/application/nests endpoint
 * and serving eggs at the top level (/api/application/eggs).
 */
class PanelVersionDetector
{
    protected static array $detectionCache = [];

    /**
     * Determine the panel version ('v1' or 'v2') using settings or live probe.
     */
    public static function detect(PterodactylClient $client, string $configuredMode = 'auto'): string
    {
        if (in_array(strtolower($configuredMode), ['v1', 'v2'])) {
            return strtolower($configuredMode);
        }

        $host = $client->getHost();
        if (isset(self::$detectionCache[$host])) {
            return self::$detectionCache[$host];
        }

        try {
            // Panel 2.x serves eggs directly at /api/application/eggs
            $res = $client->appRequest('eggs?per_page=1');
            if (isset($res['data'])) {
                self::$detectionCache[$host] = 'v2';
                return 'v2';
            }
        } catch (Exception $e) {
            // Panel 1.x returns 404 for /api/application/eggs
        }

        self::$detectionCache[$host] = 'v1';
        return 'v1';
    }

    /**
     * Clear cached detections (useful during tests or setting changes).
     */
    public static function clearCache(): void
    {
        self::$detectionCache = [];
    }

    /**
     * Resolve the appropriate endpoint path to fetch egg details with variables.
     */
    public static function getEggEndpoint(string $version, int|string $eggId, int|string|null $nestId = null): string
    {
        if ($version === 'v2' || empty($nestId)) {
            return "eggs/{$eggId}?include=variables";
        }

        return "nests/{$nestId}/eggs/{$eggId}?include=variables";
    }

    /**
     * Get the appropriate admin panel link for a server based on panel version.
     */
    public static function getAdminServerUrl(string $host, string $version, int|string $serverId): string
    {
        $base = rtrim($host, '/');
        if ($version === 'v2') {
            return "{$base}/panel/servers/{$serverId}";
        }

        return "{$base}/admin/servers/view/{$serverId}";
    }

    /**
     * Get the customer panel URL for a server.
     */
    public static function getCustomerServerUrl(string $host, string $identifier): string
    {
        $base = rtrim($host, '/');
        return "{$base}/server/{$identifier}";
    }
}
