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
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordGuildService;

class AutoJoinGuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $userId) {}

    public function handle(DiscordGuildService $guildService): void
    {
        $account = LinkedDiscordAccount::where('user_id', $this->userId)->first();
        if (!$account) {
            return;
        }

        try {
            $guildService->autoJoinUser($account);
        } catch (Exception $e) {
            Log::warning("AutoJoinGuildJob failed for user {$this->userId}: " . $e->getMessage());
        }
    }
}
