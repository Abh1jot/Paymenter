<?php

namespace Tests\Feature\Extensions;

use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Paymenter\Extensions\Servers\PterodactylV2\Console\Commands\SyncServicesCommand;
use Paymenter\Extensions\Servers\PterodactylV2\PterodactylV2;
use Paymenter\Extensions\Servers\PterodactylV2\Services\LifecycleService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PanelVersionDetector;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PasswordService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PowerService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\ProvisioningService;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PterodactylClient;
use Paymenter\Extensions\Servers\PterodactylV2\Services\ServerResolver;
use Tests\TestCase;

class PterodactylV2FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected PterodactylClient $client;
    protected string $host = 'https://panel.test';
    protected string $apiKey = 'ptla_test_application_key_abcdef123456';
    protected string $clientApiKey = 'ptlc_test_client_key_9876543210zyx';

    protected function setUp(): void
    {
        parent::setUp();
        PanelVersionDetector::clearCache();
        $this->client = new PterodactylClient($this->host, $this->apiKey, $this->clientApiKey);
    }

    public function test_test_connection_success(): void
    {
        Http::fake([
            'https://panel.test/api/application/servers*' => Http::response([
                'data' => [
                    ['attributes' => ['id' => 1]],
                ],
                'meta' => ['pagination' => ['total' => 25]],
            ], 200),
            'https://panel.test/api/application/eggs*' => Http::response([
                'data' => [
                    ['attributes' => ['id' => 1, 'name' => 'Minecraft Paper']],
                ],
            ], 200),
            'https://panel.test/api/client*' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $res = $this->client->testConnection();

        $this->assertTrue($res['success']);
        $this->assertTrue($res['is_v2']);
        $this->assertEquals(25, $res['server_count']);
        $this->assertEquals('Connected & Active', $res['client_api_status']);
    }

    public function test_test_connection_invalid_credentials_returns_clear_error(): void
    {
        Http::fake([
            'https://panel.test/api/application/servers*' => Http::response([
                'errors' => [
                    [
                        'code' => 'UnauthorizedHttpException',
                        'detail' => 'The provided API key is invalid or lacks necessary permissions.',
                    ],
                ],
            ], 401),
        ]);

        $res = $this->client->testConnection();

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('The provided API key is invalid', $res['message']);
    }

    public function test_server_resolver_finds_existing_server_by_external_id_for_legacy_services(): void
    {
        $user = User::factory()->create(['email' => 'client88@test.com']);
        $product = $this->createProduct()->product;
        $service = Service::factory()->create([
            'id' => 88,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://panel.test/api/application/servers/external/88*' => Http::response([
                'attributes' => [
                    'id' => 1042,
                    'external_id' => '88',
                    'uuid' => '9c4f1e2a-1234-5678-90ab-cdef12345678',
                    'identifier' => '9c4f1e2a',
                    'name' => 'Client 88 Minecraft',
                    'allocation' => 20,
                    'suspended' => false,
                    'limits' => [
                        'memory' => 2048,
                        'disk' => 10240,
                        'cpu' => 100,
                    ],
                    'relationships' => [
                        'allocations' => [
                            'data' => [
                                [
                                    'attributes' => [
                                        'id' => 20,
                                        'ip' => '10.0.0.1',
                                        'port' => 25565,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resolved = ServerResolver::resolve($service, $this->client, failIfNotFound: true);

        $this->assertNotNull($resolved);
        $this->assertEquals(1042, $resolved->id);
        $this->assertEquals('88', $resolved->externalId);
        $this->assertEquals('9c4f1e2a', $resolved->identifier);
        $this->assertEquals('10.0.0.1:25565', $resolved->getConnectionAddress());

        // Refresh and verify cached properties on service
        $props = $service->properties()->pluck('value', 'key')->toArray();
        $this->assertEquals('1042', $props['ptero_server_id']);
        $this->assertEquals('9c4f1e2a', $props['ptero_identifier']);
        $this->assertEquals('9c4f1e2a-1234-5678-90ab-cdef12345678', $props['ptero_server_uuid']);
    }

    public function test_provisioning_idempotency_prevents_duplicate_server_creation(): void
    {
        $user = User::factory()->create(['email' => 'client99@test.com']);
        $product = $this->createProduct()->product;
        $service = Service::factory()->create([
            'id' => 99,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => Service::STATUS_PENDING,
        ]);

        // Mock that a server already exists with external_id '99'
        Http::fake([
            'https://panel.test/api/application/servers/external/99*' => Http::response([
                'attributes' => [
                    'id' => 777,
                    'external_id' => '99',
                    'uuid' => 'existing-uuid',
                    'identifier' => 'exist999',
                    'name' => 'Existing Server',
                ],
            ], 200),
        ]);

        $result = ProvisioningService::createServer(
            $service,
            ['egg_id' => 1, 'memory' => 1024, 'disk' => 5120],
            [],
            $this->client,
            'v2'
        );

        $this->assertEquals(777, $result['server']);
        $this->assertEquals('exist999', $result['identifier']);
        $this->assertEquals('existing-uuid', $result['uuid']);
    }

    public function test_provisioning_creates_new_server_with_v2_eggs_and_external_id(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Alex',
            'last_name' => 'Developer',
            'email' => 'alex@developer.test',
        ]);
        $product = $this->createProduct()->product;
        $service = Service::factory()->create([
            'id' => 105,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => Service::STATUS_PENDING,
        ]);

        Http::fake([
            // Check existing server: not found
            'https://panel.test/api/application/servers/external/105*' => Http::response([
                'errors' => [['code' => 'NotFound', 'detail' => 'Not found']],
            ], 404),

            // Fetch Egg via v2 direct path
            'https://panel.test/api/application/eggs/5*' => Http::response([
                'attributes' => [
                    'id' => 5,
                    'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21',
                    'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar server.jar',
                    'relationships' => [
                        'variables' => [
                            'data' => [
                                [
                                    'attributes' => [
                                        'env_variable' => 'SERVER_JARFILE',
                                        'default_value' => 'server.jar',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),

            // Users endpoint: handles GET (lookup) and POST (creation)
            'https://panel.test/api/application/users*' => function ($request) {
                if ($request->method() === 'POST') {
                    return Http::response([
                        'attributes' => [
                            'id' => 312,
                            'email' => 'alex@developer.test',
                            'username' => 'alexdeveloper_1234',
                        ],
                    ], 201);
                }
                return Http::response(['data' => []], 200);
            },

            // Server creation POST
            'https://panel.test/api/application/servers' => function ($request) {
                $payload = json_decode($request->body(), true);
                if ($payload['external_id'] !== '105') {
                    return Http::response(['errors' => [['detail' => 'external_id must be string 105']]], 422);
                }
                if ($payload['egg'] !== 5 || $payload['user'] !== 312) {
                    return Http::response(['errors' => [['detail' => 'Invalid egg or user']]], 422);
                }

                return Http::response([
                    'attributes' => [
                        'id' => 2050,
                        'external_id' => '105',
                        'uuid' => 'new-server-uuid-105',
                        'identifier' => 'srv105ab',
                        'name' => 'Alex Server',
                        'limits' => [
                            'memory' => 2048,
                            'disk' => 10240,
                            'cpu' => 100,
                        ],
                        'relationships' => ['allocations' => ['data' => []]],
                    ],
                ], 201);
            },
        ]);

        $settings = [
            'egg_id' => 5,
            'memory' => 2048,
            'disk' => 10240,
            'cpu' => 100,
            'databases' => 1,
            'backups' => 1,
            'additional_allocations' => 0,
            'location_ids' => [1],
        ];

        $result = ProvisioningService::createServer($service, $settings, [], $this->client, 'v2');

        $this->assertEquals(2050, $result['server']);
        $this->assertEquals('srv105ab', $result['identifier']);

        // Check stored properties
        $props = $service->properties()->pluck('value', 'key')->toArray();
        $this->assertEquals('2050', $props['ptero_server_id']);
        $this->assertEquals('srv105ab', $props['ptero_identifier']);
        $this->assertNotEmpty($props['ptero_password']);
        $this->assertNotEmpty($props['epc_password']);
    }

    public function test_lifecycle_suspension_and_unsuspension(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct()->product;
        $service = Service::factory()->create([
            'id' => 120,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://panel.test/api/application/servers/external/120*' => Http::response([
                'attributes' => [
                    'id' => 555,
                    'external_id' => '120',
                    'uuid' => 'uuid-120',
                    'identifier' => 'ident120',
                    'name' => 'Suspend Test Server',
                ],
            ], 200),
            'https://panel.test/api/application/servers/555/suspend' => Http::response([], 204),
            'https://panel.test/api/application/servers/555/unsuspend' => Http::response([], 204),
        ]);

        $suspended = LifecycleService::suspend($service, $this->client);
        $this->assertTrue($suspended);

        $unsuspended = LifecycleService::unsuspend($service, $this->client);
        $this->assertTrue($unsuspended);
    }

    public function test_lifecycle_termination_safety_blocks_mismatched_server(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct()->product;
        $service = Service::factory()->create([
            'id' => 200,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => Service::STATUS_CANCELLED,
        ]);

        // Mock: Server was found but has conflicting external_id '999' instead of '200'
        Http::fake([
            'https://panel.test/api/application/servers/external/200*' => Http::response([
                'attributes' => [
                    'id' => 888,
                    'external_id' => '999',
                    'uuid' => 'uuid-888',
                    'identifier' => 'ident888',
                    'name' => 'Foreign Customer Server',
                ],
            ], 200),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Safety mismatch');

        LifecycleService::terminate($service, $this->client);
    }

    public function test_power_service_dispatches_signals_via_client_api(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct()->product;
        $service = Service::factory()->create([
            'id' => 300,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => Service::STATUS_ACTIVE,
        ]);

        Http::fake([
            'https://panel.test/api/application/servers/external/300*' => Http::response([
                'attributes' => [
                    'id' => 911,
                    'external_id' => '300',
                    'uuid' => 'ptero-uuid-300',
                    'identifier' => 'ptero300',
                    'name' => 'Power Server',
                ],
            ], 200),
            'https://panel.test/api/client/servers/ptero-uuid-300/power' => function ($request) {
                $body = json_decode($request->body(), true);
                if (in_array($body['signal'] ?? '', ['start', 'restart', 'stop', 'kill'])) {
                    return Http::response([], 204);
                }
                return Http::response(['errors' => [['detail' => 'Invalid signal']]], 422);
            },
        ]);

        $this->assertTrue(PowerService::sendPowerAction($service, 'start', $this->client));
        $this->assertTrue(PowerService::sendPowerAction($service, 'restart', $this->client));
        $this->assertTrue(PowerService::sendPowerAction($service, 'stop', $this->client));
        $this->assertTrue(PowerService::sendPowerAction($service, 'kill', $this->client));
    }

    public function test_power_service_rejects_missing_client_api_key(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct()->product;
        $service = Service::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $clientWithoutClientKey = new PterodactylClient($this->host, $this->apiKey, null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Client API Key is not configured');

        PowerService::sendPowerAction($service, 'start', $clientWithoutClientKey);
    }

    public function test_password_service_rotates_and_stores_both_keys(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Rotate',
            'last_name' => 'User',
            'email' => 'rotate@test.com',
        ]);
        $product = $this->createProduct()->product;
        $service = Service::factory()->create([
            'id' => 450,
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        Http::fake([
            'https://panel.test/api/application/users?filter%5Bemail%5D=rotate%40test.com' => Http::response([
                'data' => [
                    [
                        'attributes' => [
                            'id' => 881,
                            'email' => 'rotate@test.com',
                            'username' => 'rotateuser',
                            'first_name' => 'Rotate',
                            'last_name' => 'User',
                        ],
                    ],
                ],
            ], 200),
            'https://panel.test/api/application/users/881' => Http::response([], 200),
        ]);

        $newPassword = PasswordService::generateAndSyncPassword($service, $this->client);

        $this->assertNotEmpty($newPassword);
        $this->assertEquals(16, strlen($newPassword));

        // Decrypt stored password
        $decrypted = PasswordService::getStoredPassword($service);
        $this->assertEquals($newPassword, $decrypted);

        // Verify legacy epc_password key was also populated
        $props = $service->properties()->pluck('value', 'key')->toArray();
        $this->assertNotEmpty($props['epc_password']);
        $this->assertEquals($newPassword, Crypt::decryptString($props['epc_password']));
    }

    public function test_sync_services_command_audits_and_dry_runs(): void
    {
        $user = User::factory()->create(['email' => 'audit@test.com']);
        $product = $this->createProduct()->product;
        $server = Server::create([
            'name' => 'Ptero Test Server',
            'extension' => 'PterodactylV2',
            'type' => 'server',
            'enabled' => true,
        ]);
        $server->settings()->create(['key' => 'host', 'value' => 'https://panel.test']);
        $server->settings()->create(['key' => 'api_key', 'value' => 'ptla_test_application_key_abcdef123456']);
        $product->update(['server_id' => $server->id]);

        $service = Service::factory()->create([
            'id' => 777,
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        Http::fake([
            'https://panel.test/api/application/servers/external/777*' => Http::response([
                'attributes' => [
                    'id' => 4567,
                    'external_id' => '777',
                    'uuid' => 'uuid-audit-777',
                    'identifier' => 'audit777',
                    'name' => 'Audit Server',
                    'limits' => ['memory' => 1024, 'disk' => 2048, 'cpu' => 100],
                    'relationships' => ['allocations' => ['data' => []]],
                ],
            ], 200),
        ]);

        $this->app[\Illuminate\Contracts\Console\Kernel::class]->registerCommand(new SyncServicesCommand);

        $this->artisan('pterodactylv2:sync-services', [
            '--service' => '777',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN MODE')
            ->expectsOutputToContain('4567')
            ->assertExitCode(0);
    }
}
