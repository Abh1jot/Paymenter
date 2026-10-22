<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\DTO;

/**
 * Strongly-typed DTO representing live container telemetry & runtime statistics
 * retrieved from Pterodactyl Client API (/api/client/servers/{id}/resources).
 */
class ResourceUsage
{
    public function __construct(
        public string $currentState = 'offline', // running, starting, stopping, offline
        public bool $isSuspended = false,
        public int $memoryBytes = 0,
        public float $memoryMb = 0.0,
        public float $memoryLimitMb = 0.0,
        public float $memoryPercentage = 0.0,
        public float $cpuAbsolute = 0.0,
        public float $cpuLimit = 0.0,
        public float $cpuPercentage = 0.0,
        public int $diskBytes = 0,
        public float $diskMb = 0.0,
        public float $diskLimitMb = 0.0,
        public float $diskPercentage = 0.0,
        public int $networkRxBytes = 0,
        public int $networkTxBytes = 0,
        public string $networkRxFormatted = '0 B',
        public string $networkTxFormatted = '0 B',
        public int $uptimeMs = 0,
        public string $uptimeFormatted = '0s'
    ) {}

    public static function fromClientApiResponse(array $data, int $memoryLimitMb = 0, int $cpuLimit = 0, int $diskLimitMb = 0): self
    {
        $attributes = $data['attributes'] ?? $data;
        $resources = $attributes['resources'] ?? [];

        $state = strtolower($attributes['current_state'] ?? 'offline');
        $suspended = (bool) ($attributes['is_suspended'] ?? false);

        $memBytes = (int) ($resources['memory_bytes'] ?? 0);
        $memMb = round($memBytes / (1024 * 1024), 2);
        $memPercent = $memoryLimitMb > 0 ? min(100, round(($memMb / $memoryLimitMb) * 100, 1)) : 0.0;

        $cpuAbs = round((float) ($resources['cpu_absolute'] ?? 0.0), 2);
        $cpuPercent = $cpuLimit > 0 ? min(100, round(($cpuAbs / $cpuLimit) * 100, 1)) : $cpuAbs;

        $diskBytes = (int) ($resources['disk_bytes'] ?? 0);
        $diskMb = round($diskBytes / (1024 * 1024), 2);
        $diskPercent = $diskLimitMb > 0 ? min(100, round(($diskMb / $diskLimitMb) * 100, 1)) : 0.0;

        $rxBytes = (int) ($resources['network_rx_bytes'] ?? 0);
        $txBytes = (int) ($resources['network_tx_bytes'] ?? 0);
        $uptimeMs = (int) ($resources['uptime'] ?? 0);

        return new self(
            currentState: $state,
            isSuspended: $suspended,
            memoryBytes: $memBytes,
            memoryMb: $memMb,
            memoryLimitMb: (float) $memoryLimitMb,
            memoryPercentage: $memPercent,
            cpuAbsolute: $cpuAbs,
            cpuLimit: (float) $cpuLimit,
            cpuPercentage: $cpuPercent,
            diskBytes: $diskBytes,
            diskMb: $diskMb,
            diskLimitMb: (float) $diskLimitMb,
            diskPercentage: $diskPercent,
            networkRxBytes: $rxBytes,
            networkTxBytes: $txBytes,
            networkRxFormatted: self::formatBytes($rxBytes),
            networkTxFormatted: self::formatBytes($txBytes),
            uptimeMs: $uptimeMs,
            uptimeFormatted: self::formatUptime($uptimeMs)
        );
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    public static function formatUptime(int $ms): string
    {
        if ($ms <= 0) return 'Offline';
        $seconds = (int) floor($ms / 1000);
        $days = (int) floor($seconds / 86400);
        $hours = (int) floor(($seconds % 86400) / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        }
        if ($hours > 0) {
            return $secs > 0 ? "{$hours}h {$minutes}m {$secs}s" : "{$hours}h {$minutes}m";
        }
        if ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        }
        return "{$secs}s";
    }

    public function isRunning(): bool
    {
        return $this->currentState === 'running';
    }

    public function getStatusBadgeColor(): string
    {
        return match ($this->currentState) {
            'running' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            'starting' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            'stopping' => 'bg-orange-500/10 text-orange-400 border-orange-500/20',
            default => 'bg-red-500/10 text-red-400 border-red-500/20',
        };
    }
}
