<?php

namespace Tests\Unit\Extensions;

use Paymenter\Extensions\Servers\PterodactylV2\DTO\ResourceUsage;
use Paymenter\Extensions\Servers\PterodactylV2\DTO\ServerDetails;
use Paymenter\Extensions\Servers\PterodactylV2\PterodactylV2;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PanelVersionDetector;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PasswordService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PterodactylClient;
use PHPUnit\Framework\TestCase;

class PterodactylV2Test extends TestCase
{
    public function test_server_details_dto_normalizes_v1_and_v2_payloads(): void
    {
        $v2ApiResponse = [
            'attributes' => [
                'id' => 42,
                'external_id' => '100',
                'uuid' => 'b19c6e3b-7a2e-4b40-8b1a-293e5b3f7091',
                'identifier' => 'b19c6e3b',
                'name' => 'Minecraft Survival',
                'node' => 3,
                'allocation' => 15,
                'suspended' => false,
                'limits' => [
                    'memory' => 4096,
                    'swap' => 0,
                    'disk' => 20480,
                    'io' => 500,
                    'cpu' => 200,
                ],
                'feature_limits' => [
                    'databases' => 2,
                    'allocations' => 1,
                    'backups' => 3,
                ],
                'relationships' => [
                    'node' => [
                        'attributes' => ['name' => 'Node-US-East-1'],
                    ],
                    'allocations' => [
                        'data' => [
                            [
                                'attributes' => [
                                    'id' => 15,
                                    'ip' => '192.168.1.100',
                                    'port' => 25565,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $server = ServerDetails::fromApiResponse($v2ApiResponse, 'https://panel.example.com');

        $this->assertEquals(42, $server->id);
        $this->assertEquals('100', $server->externalId);
        $this->assertEquals('b19c6e3b-7a2e-4b40-8b1a-293e5b3f7091', $server->uuid);
        $this->assertEquals('b19c6e3b', $server->identifier);
        $this->assertEquals('Minecraft Survival', $server->name);
        $this->assertEquals(3, $server->nodeId);
        $this->assertEquals('Node-US-East-1', $server->nodeName);
        $this->assertEquals('192.168.1.100', $server->ip);
        $this->assertEquals(25565, $server->port);
        $this->assertEquals('192.168.1.100:25565', $server->getConnectionAddress());
        $this->assertEquals(4096, $server->memoryLimit);
        $this->assertEquals(200, $server->cpuLimit);
        $this->assertFalse($server->isSuspended);
        $this->assertEquals('https://panel.example.com/server/b19c6e3b', $server->panelUrl);
    }

    public function test_resource_usage_dto_calculations(): void
    {
        $clientApiResponse = [
            'attributes' => [
                'current_state' => 'running',
                'is_suspended' => false,
                'resources' => [
                    'memory_bytes' => 1073741824, // 1024 MB
                    'cpu_absolute' => 45.5,
                    'disk_bytes' => 5368709120, // 5120 MB
                    'network_rx_bytes' => 2097152, // 2 MB
                    'network_tx_bytes' => 1048576, // 1 MB
                    'uptime' => 3661000, // 1h 1m 1s
                ],
            ],
        ];

        $usage = ResourceUsage::fromClientApiResponse(
            $clientApiResponse,
            memoryLimitMb: 2048,
            cpuLimit: 100,
            diskLimitMb: 10240
        );

        $this->assertEquals('running', $usage->currentState);
        $this->assertTrue($usage->isRunning());
        $this->assertFalse($usage->isSuspended);
        $this->assertEquals(1024.0, $usage->memoryMb);
        $this->assertEquals(50.0, $usage->memoryPercentage);
        $this->assertEquals(45.5, $usage->cpuAbsolute);
        $this->assertEquals(45.5, $usage->cpuPercentage);
        $this->assertEquals(5120.0, $usage->diskMb);
        $this->assertEquals(50.0, $usage->diskPercentage);
        $this->assertEquals('2 MB', $usage->networkRxFormatted);
        $this->assertEquals('1 MB', $usage->networkTxFormatted);
        $this->assertEquals('1h 1m 1s', $usage->uptimeFormatted);
        $this->assertStringContainsString('emerald', $usage->getStatusBadgeColor());
    }

    public function test_uptime_formatting_for_days_and_offline(): void
    {
        $this->assertEquals('Offline', ResourceUsage::formatUptime(0));
        $this->assertEquals('45s', ResourceUsage::formatUptime(45000));
        $this->assertEquals('2m 30s', ResourceUsage::formatUptime(150000));
        $this->assertEquals('3d 4h 15m', ResourceUsage::formatUptime((3 * 86400 + 4 * 3600 + 15 * 60) * 1000));
    }

    public function test_bytes_formatting(): void
    {
        $this->assertEquals('0 B', ResourceUsage::formatBytes(0));
        $this->assertEquals('500 B', ResourceUsage::formatBytes(500));
        $this->assertEquals('1.5 KB', ResourceUsage::formatBytes(1536));
        $this->assertEquals('2.5 MB', ResourceUsage::formatBytes(2621440));
        $this->assertEquals('4 GB', ResourceUsage::formatBytes(4294967296));
    }

    public function test_token_redaction_protects_credentials(): void
    {
        $client = new PterodactylClient('https://panel.example.com', 'ptla_secret_application_token_1234567890', 'ptlc_secret_client_token_9876543210');

        $sensitive = 'Failed sending ptla_secret_application_token_1234567890 or Bearer ptlc_secret_client_token_9876543210 in request';
        $redacted = $client->redactSecrets($sensitive);

        $this->assertStringNotContainsString('ptla_secret_application_token_1234567890', $redacted);
        $this->assertStringNotContainsString('ptlc_secret_client_token_9876543210', $redacted);
        $this->assertStringContainsString('[REDACTED]', $redacted);
    }

    public function test_version_detector_resolves_endpoints(): void
    {
        // Panel 2.0 uses direct egg endpoints
        $this->assertEquals('eggs/5?include=variables', PanelVersionDetector::getEggEndpoint('v2', 5, 2));

        // Panel 1.x uses nested egg endpoints
        $this->assertEquals('nests/2/eggs/5?include=variables', PanelVersionDetector::getEggEndpoint('v1', 5, 2));

        // Admin server URLs
        $this->assertEquals('https://panel.example.com/panel/servers/42', PanelVersionDetector::getAdminServerUrl('https://panel.example.com', 'v2', 42));
        $this->assertEquals('https://panel.example.com/admin/servers/view/42', PanelVersionDetector::getAdminServerUrl('https://panel.example.com', 'v1', 42));

        // Customer server URL
        $this->assertEquals('https://panel.example.com/server/abc12345', PanelVersionDetector::getCustomerServerUrl('https://panel.example.com', 'abc12345'));
    }

    public function test_password_generator_produces_strong_entropy(): void
    {
        $pass1 = PasswordService::generateSecurePassword(16);
        $pass2 = PasswordService::generateSecurePassword(16);

        $this->assertEquals(16, strlen($pass1));
        $this->assertEquals(16, strlen($pass2));
        $this->assertNotEquals($pass1, $pass2);
    }

    public function test_migrate_option_handles_legacy_keys(): void
    {
        $extension = new PterodactylV2([]);

        $this->assertEquals(['key' => 'egg_id', 'value' => '4'], $extension->migrateOption('egg', '4'));
        $this->assertEquals(['key' => 'nest_id', 'value' => '1'], $extension->migrateOption('nest', '1'));
        $this->assertEquals(['key' => 'additional_allocations', 'value' => '2'], $extension->migrateOption('allocation', '2'));
        $this->assertEquals(['key' => 'location_ids', 'value' => '["1"]', 'type' => 'array'], $extension->migrateOption('location', '1'));
    }
}
