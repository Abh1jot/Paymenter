<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Listeners;

use App\Events\Service\Created;
use App\Models\Service;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncLinkedRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordNotificationService;

class HandleServiceCreated
{
    public function __construct(protected DiscordNotificationService $notificationService) {}

    public function handle(Created $event): void
    {
        $service = $event->service;
        if (!$service || !$service->user_id) {
            return;
        }

        if ($service->status === Service::STATUS_ACTIVE) {
            SyncUserRolesJob::dispatch($service->user_id);
            SyncLinkedRolesJob::dispatch($service->user_id);
            $this->notificationService->notifyServiceActivated($service);
        }
    }
}
