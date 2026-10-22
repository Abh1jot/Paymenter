<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\DTO;

/**
 * Strongly-typed DTO representing normalized Pterodactyl server details
 * compatible with both Pterodactyl Panel 1.x and 2.x responses.
 */
class ServerDetails
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $identifier,
        public ?string $externalId,
        public string $name,
        public ?string $description,
        public ?int $nodeId,
        public ?string $nodeName,
        public ?string $ip,
        public ?int $port,
        public array $allocations = [],
        public int $memoryLimit = 0,
        public int $swapLimit = 0,
        public int $diskLimit = 0,
        public int $ioWeight = 500,
        public int $cpuLimit = 0,
        public ?string $cpuPinning = null,
        public int $databasesLimit = 0,
        public int $allocationsLimit = 0,
        public int $backupsLimit = 0,
        public bool $isSuspended = false,
        public bool $isInstalling = false,
        public ?int $eggId = null,
        public ?string $dockerImage = null,
        public string $panelUrl = '',
        public array $raw = []
    ) {}

    /**
     * Factory constructor from Pterodactyl Application API server response.
     */
    public static function fromApiResponse(array $data, string $panelHost = ''): self
    {
        $attributes = $data['attributes'] ?? $data;
        $relationships = $attributes['relationships'] ?? [];

        // Allocations & primary IP/Port
        $allocations = [];
        $primaryIp = null;
        $primaryPort = null;

        if (isset($relationships['allocations']['data']) && is_array($relationships['allocations']['data'])) {
            foreach ($relationships['allocations']['data'] as $alloc) {
                $allocAttr = $alloc['attributes'] ?? $alloc;
                $allocations[] = [
                    'id' => $allocAttr['id'] ?? null,
                    'ip' => $allocAttr['ip'] ?? $allocAttr['ip_alias'] ?? '0.0.0.0',
                    'port' => $allocAttr['port'] ?? 0,
                    'is_default' => ($allocAttr['id'] ?? null) == ($attributes['allocation'] ?? null),
                ];
                if (($allocAttr['id'] ?? null) == ($attributes['allocation'] ?? null)) {
                    $primaryIp = $allocAttr['ip_alias'] ?? $allocAttr['ip'] ?? null;
                    $primaryPort = $allocAttr['port'] ?? null;
                }
            }
        }

        // Node
        $nodeId = $attributes['node'] ?? null;
        $nodeName = null;
        if (isset($relationships['node']['attributes']['name'])) {
            $nodeName = $relationships['node']['attributes']['name'];
        }

        $limits = $attributes['limits'] ?? [];
        $featureLimits = $attributes['feature_limits'] ?? [];
        $container = $attributes['container'] ?? [];

        $identifier = $attributes['identifier'] ?? substr($attributes['uuid'] ?? '', 0, 8);
        $panelHost = rtrim($panelHost, '/');
        $panelUrl = $panelHost ? "{$panelHost}/server/{$identifier}" : '';

        return new self(
            id: (int) ($attributes['id'] ?? 0),
            uuid: (string) ($attributes['uuid'] ?? ''),
            identifier: (string) $identifier,
            externalId: isset($attributes['external_id']) ? (string) $attributes['external_id'] : null,
            name: (string) ($attributes['name'] ?? 'Server'),
            description: $attributes['description'] ?? null,
            nodeId: $nodeId ? (int) $nodeId : null,
            nodeName: $nodeName,
            ip: $primaryIp,
            port: $primaryPort ? (int) $primaryPort : null,
            allocations: $allocations,
            memoryLimit: (int) ($limits['memory'] ?? 0),
            swapLimit: (int) ($limits['swap'] ?? 0),
            diskLimit: (int) ($limits['disk'] ?? 0),
            ioWeight: (int) ($limits['io'] ?? 500),
            cpuLimit: (int) ($limits['cpu'] ?? 0),
            cpuPinning: $limits['threads'] ?? null,
            databasesLimit: (int) ($featureLimits['databases'] ?? 0),
            allocationsLimit: (int) ($featureLimits['allocations'] ?? 0),
            backupsLimit: (int) ($featureLimits['backups'] ?? 0),
            isSuspended: (bool) ($attributes['suspended'] ?? false),
            isInstalling: (bool) (($attributes['status'] ?? null) === 'installing'),
            eggId: isset($attributes['egg']) ? (int) $attributes['egg'] : null,
            dockerImage: $container['image'] ?? null,
            panelUrl: $panelUrl,
            raw: $attributes
        );
    }

    /**
     * Formatted connection address (IP:Port).
     */
    public function getConnectionAddress(): string
    {
        if ($this->ip && $this->port) {
            return "{$this->ip}:{$this->port}";
        }

        return 'Pending Allocation';
    }
}
