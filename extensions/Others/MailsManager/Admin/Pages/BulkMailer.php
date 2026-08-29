<?php

namespace Paymenter\Extensions\Others\MailsManager\Admin\Pages;

use App\Models\Service;
use App\Models\User;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Paymenter\Extensions\Others\MailsManager\Jobs\BulkMailJob;
use Paymenter\Extensions\Others\MailsManager\Models\BulkCampaign;

class BulkMailer extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'mailsmanager::admin.bulk-mailer';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-mail-send-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-mail-send-fill';

    protected static ?string $navigationLabel = 'Bulk Mailer';

    protected static \UnitEnum|string|null $navigationGroup = 'MailsManager';

    protected static ?int $navigationSort = 2;

    // ── State ─────────────────────────────────────────────────────

    public ?array $data        = [];
    public bool   $confirmSend = false;
    public bool   $showPreview = false;

    public function mount(): void
    {
        $this->form->fill([
            'recipientType' => 'all',
        ]);
    }

    // ── Form ──────────────────────────────────────────────────────

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                TextInput::make('campaignName')
                    ->label('Campaign Name')
                    ->placeholder('e.g. August Newsletter')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('subject')
                    ->label('Subject')
                    ->placeholder('Email subject line…')
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                Textarea::make('body')
                    ->label('Email Body (HTML supported)')
                    ->placeholder("Write your email here…\n\nPersonalise with: {{ \$user->first_name }}, {{ \$user->last_name }}, {{ \$user->email }}")
                    ->rows(12)
                    ->required()
                    ->live(debounce: 500)
                    ->extraInputAttributes(['class' => 'font-mono text-xs'])
                    ->columnSpanFull(),

                Radio::make('recipientType')
                    ->label('Recipients')
                    ->options([
                        'all'    => 'All Users — every registered user',
                        'active' => 'Active Customers — users with active services',
                    ])
                    ->default('all')
                    ->required()
                    ->live()
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    // ── Computed ──────────────────────────────────────────────────

    public function getRecipientCountProperty(): int
    {
        $type = $this->data['recipientType'] ?? 'all';

        if ($type === 'active') {
            return User::whereIn(
                'id',
                Service::where('status', 'active')->distinct()->pluck('user_id')
            )->count();
        }

        return User::count();
    }

    public function getCampaignsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return BulkCampaign::latest()->get();
    }

    public function getPreviewHtmlProperty(): string
    {
        $body    = $this->data['body'] ?? '';
        $subject = $this->data['subject'] ?? '';
        $appName = config('app.name', 'Paymenter');

        if (!$body) {
            return '<p style="font-family:sans-serif;color:#888;padding:2rem;text-align:center;">Write your email body above to see a preview.</p>';
        }

        $escaped = nl2br(htmlspecialchars($body));
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 2rem auto; color: #333; line-height: 1.6; }
  .header { background:#4f46e5; color:#fff; padding:1.5rem; border-radius:8px 8px 0 0; }
  .body { border:1px solid #e5e7eb; border-top:0; padding:1.5rem; border-radius:0 0 8px 8px; }
  small { color: #9ca3af; display:block; margin-top:2rem; font-size:12px; }
</style>
</head>
<body>
  <div class="header"><strong>{$appName}</strong></div>
  <div class="body">
    <p>Hi,</p>
    {$body}
    <small>This is a preview of your campaign email from {$appName}.</small>
  </div>
</body>
</html>
HTML;
    }

    // ── Actions ───────────────────────────────────────────────────

    public function prepareSend(): void
    {
        $this->form->validate();
        $this->confirmSend = true;
    }

    public function sendCampaign(): void
    {
        $state = $this->form->getState();

        $campaign = BulkCampaign::create([
            'name'           => $state['campaignName'],
            'subject'        => $state['subject'],
            'body'           => $state['body'],
            'recipient_type' => $state['recipientType'],
            'status'         => 'pending',
            'sent_count'     => 0,
            'total_count'    => $this->recipientCount,
        ]);

        BulkMailJob::dispatch($campaign);

        $this->form->fill(['recipientType' => 'all']);
        $this->confirmSend = false;

        Notification::make()
            ->title('Campaign "' . $campaign->name . '" queued — ' . number_format($campaign->total_count) . ' recipients.')
            ->success()
            ->send();
    }

    public function cancelSend(): void
    {
        $this->confirmSend = false;
    }

    public function togglePreview(): void
    {
        $this->showPreview = !$this->showPreview;
    }
}
