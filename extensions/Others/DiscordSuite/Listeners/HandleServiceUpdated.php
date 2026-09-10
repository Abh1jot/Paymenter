<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Listeners;

use App\Events\Service\Updated;
use App\Models\Service;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncLinkedRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordNotificationService;

class HandleServiceUpdated
{
    public function __construct(protected DiscordNotificationService $notificationService) {}

    public function handle(Updated $event): void
    {
        $service = $event->service;
        if (!$service || !$service->user_id) {
            return;
        }

        // Always trigger role sync on service update as product, status, or expiry may have changed
        SyncUserRolesJob::dispatch($service->user_id);
        SyncLinkedRolesJob::dispatch($service->user_id);

        if ($service->wasChanged('status')) {
            $oldStatus = $service->getOriginal('status');
            $newStatus = $service->status;

            if ($newStatus === Service::STATUS_ACTIVE && $oldStatus !== Service::STATUS_ACTIVE) {
                if ($oldStatus === Service::STATUS_SUSPENDED) {
                    $this->notificationService->notifyServiceUnsuspended($service);
                } else {
                    $this->notificationService->notifyServiceActivated($service);
                }
            } elseif ($newStatus === Service::STATUS_SUSPENDED) {
                $this->notificationService->notifyServiceSuspended($service);
            } elseif ($newStatus === Service::STATUS_CANCELLED) {
                $this->notificationService->notifyServiceTerminated($service);
            }
        }
    }
}
