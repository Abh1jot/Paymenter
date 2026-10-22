<?php

namespace Paymenter\Extensions\Servers\PterodactylV2\Console\Commands;

use App\Models\Service;
use Exception;
use Illuminate\Console\Command;
use Paymenter\Extensions\Servers\PterodactylV2\Services\PterodactylClient;
use Paymenter\Extensions\Servers\PterodactylV2\Services\ServerResolver;

class SyncServicesCommand extends Command
{
    protected $signature = 'pterodactylv2:sync-services
                            {--service= : Synchronize only a specific Paymenter Service ID}
                            {--dry-run : Preview matches and changes without writing to database}
                            {--force : Force overwrite of existing cached identifiers}';

    protected $description = 'Audit, verify, and synchronize existing Paymenter services with their Pterodactyl v1/v2 servers.';

    public function handle(): int
    {
        $this->info('Starting Pterodactyl Service Synchronization & Verification...');
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $targetServiceId = $this->option('service');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE: No database changes will be saved.');
        }

        $query = Service::with(['product.server.settings', 'user', 'properties']);
        if ($targetServiceId) {
            $query->where('id', $targetServiceId);
        } else {
            // Find services whose product is linked to a Pterodactyl extension
            $query->whereHas('product.server', function ($q) {
                $q->whereIn('extension', ['PterodactylV2', 'Pterodactyl', 'EasyPtroClient']);
            });
        }

        $services = $query->get();

        if ($services->isEmpty()) {
            $this->warn('No matching services found.');
            return 0;
        }

        $this->info("Found {$services->count()} services to inspect.");

        $tableRows = [];
        $syncedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($services as $service) {
            $serverModel = $service->product?->server;
            if (!$serverModel) {
                $tableRows[] = [$service->id, $service->user?->email, 'N/A', 'N/A', 'Skipped: No Server Assigned'];
                $skippedCount++;
                continue;
            }

            $config = $serverModel->settings->pluck('value', 'key')->toArray();
            $host = $config['host'] ?? '';
            $apiKey = $config['api_key'] ?? '';

            if (empty($host) || empty($apiKey)) {
                $tableRows[] = [$service->id, $service->user?->email, 'N/A', 'N/A', 'Skipped: Incomplete Credentials'];
                $skippedCount++;
                continue;
            }

            $client = new PterodactylClient($host, $apiKey, $config['client_api_key'] ?? null);

            try {
                $alreadyMapped = ServerResolver::hasStoredMapping($service);
                if ($alreadyMapped && !$force && !$isDryRun) {
                    $props = $service->properties->pluck('value', 'key')->toArray();
                    $tableRows[] = [
                        $service->id,
                        $service->user?->email,
                        $props['ptero_server_id'] ?? $props['server_id'] ?? 'Mapped',
                        $props['ptero_identifier'] ?? 'Cached',
                        'Already Mapped (Use --force to refresh)',
                    ];
                    $skippedCount++;
                    continue;
                }

                $server = ServerResolver::resolve($service, $client, failIfNotFound: false);

                if ($server) {
                    if (!$isDryRun) {
                        ServerResolver::syncProperties($service, $server);
                    }

                    $tableRows[] = [
                        $service->id,
                        $service->user?->email,
                        (string) $server->id,
                        $server->identifier,
                        $isDryRun ? 'Matched (Dry Run)' : 'Successfully Synchronized',
                    ];
                    $syncedCount++;
                } else {
                    $tableRows[] = [
                        $service->id,
                        $service->user?->email,
                        'Not Found',
                        'Not Found',
                        'Error: Server not found on Pterodactyl',
                    ];
                    $failedCount++;
                }
            } catch (Exception $e) {
                $tableRows[] = [
                    $service->id,
                    $service->user?->email,
                    'Error',
                    'Error',
                    'Exception: ' . $e->getMessage(),
                ];
                $failedCount++;
            }
        }

        $this->table(
            ['Service ID', 'Customer Email', 'Ptero Server ID', 'Identifier', 'Status / Action'],
            $tableRows
        );

        $this->newLine();
        $this->info("Synchronization Complete: {$syncedCount} matched/synced, {$failedCount} unresolved, {$skippedCount} skipped.");

        return $failedCount > 0 ? 1 : 0;
    }
}
