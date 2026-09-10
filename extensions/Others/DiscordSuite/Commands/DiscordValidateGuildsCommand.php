<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Commands;

use Exception;
use Illuminate\Console\Command;
use Paymenter\Extensions\Others\DiscordSuite\Models\DiscordGuild;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordApiService;

class DiscordValidateGuildsCommand extends Command
{
    protected $signature = 'discord:validate-guilds';

    protected $description = 'Validate Discord bot presence in configured guilds and update guild membership status of linked users';

    public function handle(DiscordApiService $apiService): int
    {
        $this->info('Validating Discord guilds...');

        $guilds = DiscordGuild::all();
        if ($guilds->isEmpty()) {
            $this->warn('No Discord guilds configured in discord_guilds table.');
            return Command::SUCCESS;
        }

        foreach ($guilds as $guild) {
            try {
                $roles = $apiService->getGuildRoles($guild->guild_id);
                $guild->update(['bot_present' => !empty($roles)]);
                $this->info("Guild: {$guild->name} ({$guild->guild_id}) -> Bot Present: OK (" . count($roles) . " roles found)");
            } catch (Exception $e) {
                $guild->update(['bot_present' => false]);
                $this->error("Guild: {$guild->name} ({$guild->guild_id}) -> Error: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
