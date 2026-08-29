<?php

namespace Paymenter\Extensions\Others\MailsManager\Admin\Pages;

use App\Models\Service;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\MailsManager\Jobs\BulkMailJob;
use Paymenter\Extensions\Others\MailsManager\Models\BulkCampaign;

class BulkMailer extends Page
{
    protected string $view = 'mailsmanager::admin.bulk-mailer';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-mail-send-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-mail-send-fill';

    protected static ?string $navigationLabel = 'Bulk Mailer';

    protected static \UnitEnum|string|null $navigationGroup = 'MailsManager';

    protected static ?int $navigationSort = 2;

    // ── Form state ────────────────────────────────────────────────

    public string $campaignName    = '';
    public string $subject         = '';
    public string $body            = '';
    public string $recipientType   = 'all';  // 'all' | 'active'
    public bool   $confirmSend     = false;
    public bool   $showPreview     = false;

    // ── Computed ──────────────────────────────────────────────────

    /**
     * Count of users that will receive the email based on current recipientType.
     */
    public function getRecipientCountProperty(): int
    {
        if ($this->recipientType === 'active') {
            return User::whereIn(
                'id',
                Service::where('status', 'active')->distinct()->pluck('user_id')
            )->count();
        }
        return User::count();
    }

    /**
     * All past campaigns, newest first.
     */
    public function getCampaignsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return BulkCampaign::latest()->get();
    }

    /**
     * Preview HTML for the current body.
     */
    public function getPreviewHtmlProperty(): string
    {
        if (!$this->body) {
            return '<p style="font-family:sans-serif;color:#888;padding:2rem;text-align:center;">Write your email body above to preview it here.</p>';
        }

        $appName = config('app.name', 'Paymenter');
        $body = $this->body;

        // Wrap in the same style as SystemMail
        $bodyHtml = "<p>Hi,</p>\n{$body}\n<small>This is an automated message sent from {$appName}</small>";

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 2rem auto; color: #333; }
  small { color: #888; display: block; margin-top: 2rem; }
</style>
</head>
<body>{$bodyHtml}</body>
</html>
HTML;
    }

    // ── Actions ───────────────────────────────────────────────────

    /**
     * Validate and show confirmation before sending.
     */
    public function prepareSend(): void
    {
        $this->validate([
            'campaignName'  => 'required|string|max:255',
            'subject'       => 'required|string|max:255',
            'body'          => 'required|string',
            'recipientType' => 'required|in:all,active',
        ]);

        $this->confirmSend = true;
    }

    /**
     * Create campaign record and dispatch the bulk mail job.
     */
    public function sendCampaign(): void
    {
        $campaign = BulkCampaign::create([
            'name'           => $this->campaignName,
            'subject'        => $this->subject,
            'body'           => $this->body,
            'recipient_type' => $this->recipientType,
            'status'         => 'pending',
            'sent_count'     => 0,
            'total_count'    => $this->recipientCount,
        ]);

        BulkMailJob::dispatch($campaign);

        // Reset form
        $this->campaignName  = '';
        $this->subject       = '';
        $this->body          = '';
        $this->recipientType = 'all';
        $this->confirmSend   = false;

        Notification::make()
            ->title('Campaign "' . $campaign->name . '" queued — ' . $campaign->total_count . ' recipients.')
            ->success()
            ->send();
    }

    /**
     * Cancel the confirmation dialog.
     */
    public function cancelSend(): void
    {
        $this->confirmSend = false;
    }

    /**
     * Toggle the preview panel.
     */
    public function togglePreview(): void
    {
        $this->showPreview = !$this->showPreview;
    }
}
