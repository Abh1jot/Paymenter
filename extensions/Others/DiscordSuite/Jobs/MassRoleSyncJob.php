<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;

class MassRoleSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        // Chunk through all linked accounts in batches of 50 to avoid burst rate limits
        LinkedDiscordAccount::chunk(50, function ($accounts) {
            $delay = 0;
            foreach ($accounts as $account) {
                SyncUserRolesJob::dispatch($account->user_id)->delay(now()->addSeconds($delay));
                $delay += 2; // Stagger calls by 2 seconds
            }
        });
    }
}
