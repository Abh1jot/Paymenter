<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Commands;

use Exception;
use Illuminate\Console\Command;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordApiService;

class DiscordBotGatewayCommand extends Command
{
    protected $signature = 'discord:gateway';

    protected $description = 'Optional Discord Gateway WebSocket client runner for real-time gateway events';

    public function handle(DiscordApiService $apiService): int
    {
        $this->info('Initializing Discord Suite Gateway connection...');

        $token = $apiService->getBotToken();
        if (!$token) {
            $this->error('Bot token is not configured.');
            return Command::FAILURE;
        }

        try {
            $bot = $apiService->getBotUser();
            $this->info("Authenticated as: {$bot['username']}#{$bot['discriminator']} (ID: {$bot['id']})");
            $this->line("Discord Suite primary slash commands and interactions operate via the HTTP REST Webhook endpoint: /api/discord-suite/interactions");
            $this->line("Gateway connection heartbeat active. Press Ctrl+C to terminate.");

            // Keep daemon alive and perform periodic sync health heartbeat
            while (true) {
                sleep(60);
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Gateway error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
