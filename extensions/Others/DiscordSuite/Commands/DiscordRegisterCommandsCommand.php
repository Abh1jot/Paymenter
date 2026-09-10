<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Commands;

use Exception;
use Illuminate\Console\Command;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordApiService;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordInteractionService;

class DiscordRegisterCommandsCommand extends Command
{
    protected $signature = 'discord:register-commands {--guild= : Register for a specific Guild ID instead of globally}';

    protected $description = 'Register all Discord Suite customer and admin slash commands with Discord API';

    public function handle(DiscordApiService $apiService, DiscordInteractionService $interactionService): int
    {
        $this->info('Registering Discord Suite slash commands...');

        $commands = $interactionService->getCommandDefinitions();
        $guildId = $this->option('guild');

        try {
            if ($guildId) {
                $this->line("Registering " . count($commands) . " commands to Guild ID: {$guildId}...");
                $apiService->registerGuildCommands($guildId, $commands);
            } else {
                $this->line("Registering " . count($commands) . " global commands (propagation may take a few minutes)...");
                $apiService->registerGlobalCommands($commands);
            }

            $this->info('✅ All slash commands successfully registered with Discord!');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Failed to register commands: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
