<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;
use Paymenter\Extensions\Others\DiscordSuite\Services\RoleSyncEngine;

class SyncUserRolesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 180];

    public function __construct(public int $userId) {}

    public function handle(RoleSyncEngine $engine): void
    {
        $account = LinkedDiscordAccount::where('user_id', $this->userId)->first();
        if (!$account) {
            return;
        }

        try {
            $engine->syncAccount($account);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'rate limited')) {
                $this->release(10);
                return;
            }

            Log::error("SyncUserRolesJob failed for user {$this->userId}: " . $e->getMessage());
            throw $e;
        }
    }
}
