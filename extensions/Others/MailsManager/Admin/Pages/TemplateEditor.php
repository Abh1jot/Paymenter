<?php

namespace Paymenter\Extensions\Others\MailsManager\Admin\Pages;

use App\Mail\Mail as PaymenterMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TemplateEditor extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'mailsmanager::admin.template-editor';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-mail-settings-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-mail-settings-fill';

    protected static ?string $navigationLabel = 'Email Templates';

    protected static \UnitEnum|string|null $navigationGroup = 'MailsManager';

    protected static ?int $navigationSort = 1;

    // ── State ─────────────────────────────────────────────────────

    public ?int    $editingId  = null;
    public bool    $showPreview = false;
    public ?array  $data        = [];

    // ── Form ──────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('subject')
                    ->label('Subject Line')
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                Textarea::make('body')
                    ->label('Email Body (Markdown or HTML)')
                    ->rows(18)
                    ->required()
                    ->live(debounce: 500)
                    ->extraInputAttributes(['class' => 'font-mono text-xs'])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    // ── Computed ──────────────────────────────────────────────────

    public function getTemplatesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationTemplate::orderBy('key')->get();
    }

    public function getEditingTemplateProperty(): ?NotificationTemplate
    {
        if (!$this->editingId) return null;
        return NotificationTemplate::find($this->editingId);
    }

    public function getAvailableVariablesProperty(): array
    {
        $template = $this->editingTemplate;
        if (!$template) return [];

        $variableMap = [
            'new_invoice_created'           => ['$user', '$invoice', '$items', '$total'],
            'invoice_paid'                  => ['$user', '$invoice', '$items', '$total'],
            'invoice_payment_failed'        => ['$user', '$invoice', '$items', '$total'],
            'new_order_created'             => ['$user', '$order', '$items', '$total'],
            'new_server_created'            => ['$user', '$service'],
            'server_suspended'              => ['$user', '$service'],
            'server_terminated'             => ['$user', '$service'],
            'new_ticket_message'            => ['$user', '$ticketMessage'],
            'email_verification'            => ['$user', '$url'],
            'password_reset'                => ['$user'],
            'new_login_detected'            => ['$user'],
            'service_cancellation_received' => ['$user', '$cancellation', '$service'],
        ];

        $examples = [
            '$user'          => ['$user->first_name', '$user->last_name', '$user->email'],
            '$invoice'       => ['$invoice->number', '$invoice->total', '$invoice->due_date'],
            '$service'       => ['$service->name', '$service->status'],
            '$order'         => ['$order->id'],
            '$items'         => ['@foreach($items as $item) ... @endforeach'],
            '$total'         => ['$total'],
            '$url'           => ['$url'],
            '$ticketMessage' => ['$ticketMessage->body'],
            '$cancellation'  => ['$cancellation->reason'],
        ];

        $result = [];
        foreach ($variableMap[$template->key] ?? ['$user'] as $var) {
            $result[$var] = $examples[$var] ?? [$var];
        }
        return $result;
    }

    public function getPreviewHtmlProperty(): string
    {
        $body = $this->data['body'] ?? '';
        if (!$body) {
            return '<p style="font-family:sans-serif;color:#888;padding:2rem;text-align:center;">Start typing to see preview...</p>';
        }

        try {
            $template = new NotificationTemplate([
                'key'     => 'preview',
                'name'    => 'Preview',
                'subject' => $this->data['subject'] ?? 'Preview',
                'body'    => $body,
                'enabled' => true,
            ]);

            $dummy = [
                'user'    => (object)['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'name' => 'John Doe'],
                'invoice' => (object)['id' => 1001, 'number' => 'INV-1001', 'total' => '49.99', 'formattedTotal' => '$49.99', 'due_date' => now()->addDays(14)->format('Y-m-d')],
                'service' => (object)['id' => 1, 'name' => 'Sample Service', 'status' => 'active'],
                'order'   => (object)['id' => 1, 'formattedTotal' => '$49.99'],
                'url'     => '#',
                'items'   => collect([]),
                'total'   => '$49.99',
            ];

            return (new PaymenterMail($template, $dummy))->render();
        } catch (\Throwable $e) {
            return '<p style="font-family:sans-serif;color:#dc2626;padding:2rem;"><strong>Preview error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }

    // ── Actions ───────────────────────────────────────────────────

    public function editTemplate(int $id): void
    {
        $template = NotificationTemplate::findOrFail($id);
        $this->editingId   = $template->id;
        $this->showPreview = false;

        $this->form->fill([
            'subject' => $template->subject,
            'body'    => $template->body,
        ]);
    }

    public function saveTemplate(): void
    {
        $state    = $this->form->getState();
        $template = NotificationTemplate::findOrFail($this->editingId);

        $template->update([
            'subject' => $state['subject'],
            'body'    => $state['body'],
        ]);

        Notification::make()->title('Template saved.')->success()->send();
    }

    public function sendTestEmail(): void
    {
        /** @var User $admin */
        $admin = Auth::user();
        if (!$admin) {
            Notification::make()->title('Not authenticated.')->danger()->send();
            return;
        }

        try {
            $state    = $this->form->getState();
            $template = new NotificationTemplate([
                'key'          => 'test',
                'name'         => 'Test',
                'subject'      => $state['subject'] ?: 'Test Email',
                'body'         => $state['body'],
                'enabled'      => true,
                'mail_enabled' => \App\Enums\NotificationEnabledStatus::Force,
                'cc'           => [],
                'bcc'          => [],
            ]);

            \App\Helpers\NotificationHelper::sendEmailNotification($template, ['user' => $admin], $admin);

            Notification::make()->title('Test email sent to ' . $admin->email)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Failed: ' . $e->getMessage())->danger()->send();
        }
    }

    public function togglePreview(): void
    {
        $this->showPreview = !$this->showPreview;
    }

    public function closeEditor(): void
    {
        $this->editingId   = null;
        $this->showPreview = false;
        $this->data        = [];
        $this->form->fill();
    }
}
