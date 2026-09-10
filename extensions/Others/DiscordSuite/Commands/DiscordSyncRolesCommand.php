<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Commands;

use Illuminate\Console\Command;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\MassRoleSyncJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class DiscordSyncRolesCommand extends Command
{
    protected $signature = 'discord:sync-roles {--user= : Specific user ID to synchronize}';

    protected $description = 'Synchronize Discord roles for linked accounts according to product, category, and tier rules';

    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId) {
            $account = LinkedDiscordAccount::where('user_id', $userId)->first();
            if (!$account) {
                $this->error("No linked Discord account found for User ID #{$userId}.");
                return Command::FAILURE;
            }

            $this->info("Dispatching role sync job for user #{$userId} (@{$account->discord_username})...");
            SyncUserRolesJob::dispatch($userId);
            $this->info('Job queued.');

            return Command::SUCCESS;
        }

        $count = LinkedDiscordAccount::count();
        $this->info("Dispatching mass role sync job for {$count} linked accounts...");
        MassRoleSyncJob::dispatch();
        $this->info('Mass sync job queued.');

        return Command::SUCCESS;
    }
}
