<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\NotificationLogRepository;

class RenewalReminderService
{
    public function __construct(
        protected DiscordNotificationService $notificationService,
        protected NotificationLogRepository $notificationLogRepository
    ) {}

    /**
     * Checks all active services and sends renewal reminders at configured intervals.
     */
    public function processReminders(): array
    {
        $milestones = [14, 7, 3, 1, 0];
        $summary = [];

        foreach ($milestones as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $services = Service::where('status', Service::STATUS_ACTIVE)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', $targetDate)
                ->with(['user', 'product'])
                ->get();

            $sentCount = 0;
            foreach ($services as $service) {
                // Ensure user has linked discord account
                if (!$service->user || !$service->user->discordAccount) {
                    continue;
                }

                $type = "reminder_{$days}d";

                // Check idempotency: don't duplicate reminders on the same day
                if ($this->notificationLogRepository->wasNotificationSentToday($type, Service::class, $service->id)) {
                    continue;
                }

                $this->notificationService->notifyRenewalReminder($service, $days);
                $sentCount++;
            }

            $summary["{$days}_days"] = $sentCount;
        }

        Log::info("Discord Suite renewal reminders processed.", $summary);

        return $summary;
    }
}
