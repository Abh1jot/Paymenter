<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Listeners;

use App\Events\TicketMessage\Created;
use Paymenter\Extensions\Others\DiscordSuite\Services\DiscordNotificationService;

class HandleTicketMessageCreated
{
    public function __construct(protected DiscordNotificationService $notificationService) {}

    public function handle(Created $event): void
    {
        $message = $event->ticketMessage;
        if (!$message || !$message->ticket) {
            return;
        }

        $ticket = $message->ticket;

        // Only notify customer if the reply was sent by staff (i.e. not the customer themselves)
        if ($message->user_id !== $ticket->user_id) {
            $this->notificationService->notifyTicketReply($ticket, $message->message);
        }
    }
}
