<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Listeners;

use App\Events\Invoice\Finalized;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordNotificationService;

class HandleInvoiceFinalized
{
    public function __construct(protected DiscordNotificationService $notificationService) {}

    public function handle(Finalized $event): void
    {
        $invoice = $event->invoice;
        if (!$invoice || !$invoice->user_id) {
            return;
        }

        $this->notificationService->notifyNewInvoice($invoice);
    }
}
