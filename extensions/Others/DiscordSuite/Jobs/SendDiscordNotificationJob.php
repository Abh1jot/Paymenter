<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Jobs;

use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordNotificationService;

class SendDiscordNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $userId,
        public string $type,
        public array $embed,
        public array $buttons = [],
        public ?string $refType = null,
        public ?int $refId = null
    ) {}

    public function handle(DiscordNotificationService $notificationService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            return;
        }

        try {
            $notificationService->sendDM(
                $user,
                $this->type,
                $this->embed,
                $this->buttons,
                $this->refType,
                $this->refId
            );
        } catch (Exception $e) {
            Log::error("SendDiscordNotificationJob error: " . $e->getMessage());
            throw $e;
        }
    }
}
