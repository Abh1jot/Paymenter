<?php

namespace Paymenter\Extensions\Others\DiscordSuite\Services;

use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Paymenter\Extensions\Others\DiscordSuite\Models\LinkedDiscordAccount;
use Paymenter\Extensions\Others\DiscordSuite\Repositories\NotificationLogRepository;

class DiscordNotificationService
{
    public function __construct(
        protected DiscordApiService $apiService,
        protected NotificationLogRepository $notificationLogRepository
    ) {}

    public function isNotificationEnabled(string $type): bool
    {
        $extension = ExtensionHelper::getExtension('other', 'DiscordSuite');
        if (!$extension) {
            return false;
        }

        $enabledEvents = $extension->config('enabled_notifications') ?? [];

        // If array of keys, check if in array
        return in_array($type, (array) $enabledEvents);
    }

    public function getEmbedColor(): int
    {
        $extension = ExtensionHelper::getExtension('other', 'DiscordSuite');
        $hex = $extension?->config('embed_color') ?: '#5865F2';
        $hex = ltrim($hex, '#');

        return hexdec($hex);
    }

    /**
     * Send direct message to a linked user with rich embed and action buttons.
     */
    public function sendDM(User $user, string $type, array $embed, array $buttons = [], ?string $refType = null, ?int $refId = null): bool
    {
        if (!$this->isNotificationEnabled($type)) {
            return false;
        }

        /** @var LinkedDiscordAccount|null $account */
        $account = $user->discordAccount ?? LinkedDiscordAccount::where('user_id', $user->id)->first();
        if (!$account || !$account->discord_user_id) {
            return false;
        }

        try {
            // 1. Create or get DM channel
            $dmChannelId = $this->apiService->createDMChannel($account->discord_user_id);

            // 2. Prepare payload
            $embed['color'] = $embed['color'] ?? $this->getEmbedColor();
            $embed['footer'] = $embed['footer'] ?? [
                'text' => config('app.name', 'Paymenter Billing') . ' • Discord Suite',
            ];
            $embed['timestamp'] = now()->toIso8601String();

            $payload = ['embeds' => [$embed]];

            // Format Action Buttons (Link Button Components)
            if (!empty($buttons)) {
                $actionRow = ['type' => 1, 'components' => []];
                foreach ($buttons as $btn) {
                    $actionRow['components'][] = [
                        'type' => 2, // BUTTON
                        'style' => 5, // LINK
                        'label' => $btn['label'],
                        'url' => $btn['url'],
                    ];
                }
                $payload['components'] = [$actionRow];
            }

            // 3. Send Message
            $msg = $this->apiService->sendChannelMessage($dmChannelId, $payload);

            // 4. Log Success
            $this->notificationLogRepository->logSent(
                $user->id,
                $account->discord_user_id,
                $type,
                $refType,
                $refId,
                $dmChannelId,
                $msg['id'] ?? null
            );

            return true;
        } catch (Exception $e) {
            $isDmBlocked = str_contains($e->getMessage(), '50007') || str_contains($e->getMessage(), 'Cannot send messages to this user');
            $status = $isDmBlocked ? 'failed_dm' : 'failed_other';

            $this->notificationLogRepository->logFailed(
                $user->id,
                $account->discord_user_id,
                $type,
                $status,
                $e->getMessage(),
                $refType,
                $refId
            );

            Log::warning("Discord DM failed for user {$user->id} ({$type}): " . $e->getMessage());

            // Optional Fallback to staff log channel or notification
            $this->sendLogToStaffChannel("⚠️ Failed delivering DM to {$user->name} ({$type}): " . ($isDmBlocked ? 'DMs disabled' : $e->getMessage()));

            return false;
        }
    }

    /**
     * Send log or announcement message to configured staff Discord channel.
     */
    public function sendLogToStaffChannel(string $content, ?array $embed = null): void
    {
        $extension = ExtensionHelper::getExtension('other', 'DiscordSuite');
        $channelId = $extension?->config('staff_log_channel_id');
        if (!$channelId) {
            return;
        }

        try {
            $payload = ['content' => $content];
            if ($embed) {
                $payload['embeds'] = [$embed];
            }
            $this->apiService->sendChannelMessage($channelId, $payload);
        } catch (Exception $e) {
            Log::warning("Failed to send staff Discord log: " . $e->getMessage());
        }
    }

    // High Level Notification Builders

    public function notifyNewInvoice(Invoice $invoice): void
    {
        $user = $invoice->user;
        $url = route('invoices.show', $invoice->id);

        $embed = [
            'title' => "📄 New Invoice #{$invoice->id} Generated",
            'description' => "A new invoice has been generated for your account.",
            'fields' => [
                ['name' => 'Invoice Total', 'value' => "{$invoice->formatted_total}", 'inline' => true],
                ['name' => 'Due Date', 'value' => $invoice->due_at?->format('M d, Y') ?? 'Immediate', 'inline' => true],
                ['name' => 'Status', 'value' => ucfirst($invoice->status), 'inline' => true],
            ],
        ];

        $buttons = [['label' => 'View & Pay Invoice', 'url' => $url]];
        $this->sendDM($user, 'invoice_created', $embed, $buttons, Invoice::class, $invoice->id);
    }

    public function notifyInvoicePaid(Invoice $invoice): void
    {
        $user = $invoice->user;
        $url = route('invoices.show', $invoice->id);

        $embed = [
            'title' => "✅ Invoice #{$invoice->id} Paid",
            'description' => "Thank you! We have received your payment.",
            'fields' => [
                ['name' => 'Amount Paid', 'value' => "{$invoice->formatted_total}", 'inline' => true],
                ['name' => 'Payment Date', 'value' => now()->format('M d, Y H:i'), 'inline' => true],
            ],
        ];

        $buttons = [['label' => 'View Receipt', 'url' => $url]];
        $this->sendDM($user, 'invoice_paid', $embed, $buttons, Invoice::class, $invoice->id);
    }

    public function notifyServiceActivated(Service $service): void
    {
        $user = $service->user;
        $url = route('services.show', $service->id);

        $embed = [
            'title' => "🚀 Service Activated: {$service->product->name}",
            'description' => "Your service is now fully active and ready to use!",
            'fields' => [
                ['name' => 'Product', 'value' => $service->product->name, 'inline' => true],
                ['name' => 'Renewal Date', 'value' => $service->expires_at?->format('M d, Y') ?? 'Never', 'inline' => true],
            ],
        ];

        $buttons = [['label' => 'Open Service Details', 'url' => $url]];
        $this->sendDM($user, 'service_activated', $embed, $buttons, Service::class, $service->id);
    }

    public function notifyServiceSuspended(Service $service): void
    {
        $user = $service->user;
        $url = route('services.show', $service->id);

        $embed = [
            'title' => "⚠️ Service Suspended: {$service->product->name}",
            'description' => "Your service has been suspended due to overdue payment. Please pay your pending invoice to restore your service immediately.",
            'fields' => [
                ['name' => 'Service ID', 'value' => "#{$service->id}", 'inline' => true],
                ['name' => 'Product', 'value' => $service->product->name, 'inline' => true],
            ],
        ];

        $buttons = [['label' => 'Reactivate Service', 'url' => $url]];
        $this->sendDM($user, 'service_suspended', $embed, $buttons, Service::class, $service->id);
    }

    public function notifyServiceUnsuspended(Service $service): void
    {
        $user = $service->user;
        $url = route('services.show', $service->id);

        $embed = [
            'title' => "🎉 Service Reactivated: {$service->product->name}",
            'description' => "Your service suspension has been lifted and it is back online!",
            'fields' => [
                ['name' => 'Service ID', 'value' => "#{$service->id}", 'inline' => true],
            ],
        ];

        $buttons = [['label' => 'Manage Service', 'url' => $url]];
        $this->sendDM($user, 'service_unsuspended', $embed, $buttons, Service::class, $service->id);
    }

    public function notifyServiceTerminated(Service $service): void
    {
        $user = $service->user;

        $embed = [
            'title' => "🛑 Service Terminated: {$service->product->name}",
            'description' => "Your service has been terminated and its server data removed.",
            'fields' => [
                ['name' => 'Service ID', 'value' => "#{$service->id}", 'inline' => true],
            ],
        ];

        $this->sendDM($user, 'service_terminated', $embed, [], Service::class, $service->id);
    }

    public function notifyTicketReply(Ticket $ticket, string $replyPreview): void
    {
        $user = $ticket->user;
        $url = route('tickets.show', $ticket->id);

        $embed = [
            'title' => "💬 New Reply on Ticket #{$ticket->id}",
            'description' => "A support agent has replied to your ticket:\n\n> " . substr($replyPreview, 0, 300) . (strlen($replyPreview) > 300 ? '...' : ''),
            'fields' => [
                ['name' => 'Subject', 'value' => $ticket->subject, 'inline' => true],
                ['name' => 'Department', 'value' => ucfirst($ticket->department ?? 'General'), 'inline' => true],
            ],
        ];

        $buttons = [['label' => 'View Ticket', 'url' => $url]];
        $this->sendDM($user, 'ticket_reply', $embed, $buttons, Ticket::class, $ticket->id);
    }

    public function notifyCreditAdded(User $user, string $amountFormatted): void
    {
        $url = route('account.credits');

        $embed = [
            'title' => "💰 Credits Added to Your Account",
            'description' => "New credits have been successfully credited to your billing balance.",
            'fields' => [
                ['name' => 'Amount Added', 'value' => $amountFormatted, 'inline' => true],
            ],
        ];

        $buttons = [['label' => 'View Balance', 'url' => $url]];
        $this->sendDM($user, 'credit_added', $embed, $buttons, User::class, $user->id);
    }

    public function notifyRenewalReminder(Service $service, int $daysRemaining): void
    {
        $user = $service->user;
        $url = route('services.show', $service->id);

        $timeText = $daysRemaining === 0
            ? "TODAY"
            : ($daysRemaining === 1 ? "TOMORROW (1 day)" : "in {$daysRemaining} days");

        $embed = [
            'title' => "⏰ Service Renewal Reminder: {$service->product->name}",
            'description' => "Your service will expire **{$timeText}** ({$service->expires_at?->format('M d, Y')}). Renew today to prevent service interruption or suspension.",
            'fields' => [
                ['name' => 'Service', 'value' => $service->product->name, 'inline' => true],
                ['name' => 'Renewal Amount', 'value' => "{$service->formatted_price}", 'inline' => true],
                ['name' => 'Expiration Date', 'value' => $service->expires_at?->format('M d, Y') ?? 'N/A', 'inline' => true],
            ],
        ];

        $buttons = [['label' => 'Renew Now', 'url' => $url]];
        $this->sendDM($user, "reminder_{$daysRemaining}d", $embed, $buttons, Service::class, $service->id);
    }
}
