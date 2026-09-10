<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Listeners;

use App\Events\Invoice\Paid;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncLinkedRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Jobs\SyncUserRolesJob;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordNotificationService;

class HandleInvoicePaid
{
    public function __construct(protected DiscordNotificationService $notificationService) {}

    public function handle(Paid $event): void
    {
        $invoice = $event->invoice;
        if (!$invoice || !$invoice->user_id) {
            return;
        }

        // Trigger role sync & linked roles update
        SyncUserRolesJob::dispatch($invoice->user_id);
        SyncLinkedRolesJob::dispatch($invoice->user_id);

        // Send payment confirmation DM
        $this->notificationService->notifyInvoicePaid($invoice);
    }
}
