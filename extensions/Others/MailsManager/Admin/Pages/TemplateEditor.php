<?php

namespace Paymenter\Extensions\Others\MailsManager\Admin\Pages;

use App\Helpers\NotificationHelper;
use App\Mail\Mail as PaymenterMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TemplateEditor extends Page
{
    protected string $view = 'mailsmanager::admin.template-editor';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-mail-settings-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-mail-settings-fill';

    protected static ?string $navigationLabel = 'Email Templates';

    protected static \UnitEnum|string|null $navigationGroup = 'MailsManager';

    protected static ?int $navigationSort = 1;

    // ── State ─────────────────────────────────────────────────────

    /** ID of the template currently being edited */
    public ?int $editingId = null;

    /** Current editor state */
    public string $editSubject = '';
    public string $editBody    = '';

    /** Preview: is the preview panel visible */
    public bool $showPreview = false;

    /** Sending state for test email */
    public bool $testSending = false;

    // ── Computed ──────────────────────────────────────────────────

    /** All notification templates (used to build the list) */
    public function getTemplatesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return NotificationTemplate::orderBy('name')->get();
    }

    /** Currently selected template model */
    public function getEditingTemplateProperty(): ?NotificationTemplate
    {
        if (!$this->editingId) return null;
        return NotificationTemplate::find($this->editingId);
    }

    /** Available variables for the selected template (hint panel) */
    public function getAvailableVariablesProperty(): array
    {
        $template = $this->editingTemplate;
        if (!$template) return [];

        // Map known template keys to their available Blade variables
        $variableMap = [
            'new_invoice_created'              => ['$user', '$invoice', '$items', '$total'],
            'invoice_paid'                     => ['$user', '$invoice', '$items', '$total'],
            'invoice_payment_failed'           => ['$user', '$invoice', '$items', '$total'],
            'new_order_created'                => ['$user', '$order', '$items', '$total'],
            'new_server_created'               => ['$user', '$service'],
            'server_suspended'                 => ['$user', '$service'],
            'server_terminated'                => ['$user', '$service'],
            'new_ticket_message'               => ['$user', '$ticketMessage'],
            'email_verification'               => ['$user', '$url'],
            'password_reset'                   => ['$user'],
            'new_login_detected'               => ['$user'],
            'service_cancellation_received'    => ['$user', '$cancellation', '$service'],
        ];

        $key = $template->key;
        $vars = $variableMap[$key] ?? ['$user'];

        // Build a friendly list with example values
        $examples = [
            '$user'          => ['$user->first_name', '$user->last_name', '$user->email'],
            '$invoice'       => ['$invoice->number', '$invoice->total', '$invoice->due_date'],
            '$service'       => ['$service->name', '$service->status'],
            '$order'         => ['$order->id', '$order->total'],
            '$items'         => ['@foreach($items as $item) ... @endforeach'],
            '$total'         => ['$total'],
            '$url'           => ['$url'],
            '$ticketMessage' => ['$ticketMessage->body'],
            '$cancellation'  => ['$cancellation->reason'],
        ];

        $result = [];
        foreach ($vars as $var) {
            $result[$var] = $examples[$var] ?? [$var];
        }
        return $result;
    }

    // ── Actions ───────────────────────────────────────────────────

    /**
     * Open a template for editing.
     */
    public function editTemplate(int $id): void
    {
        $template = NotificationTemplate::findOrFail($id);
        $this->editingId  = $template->id;
        $this->editSubject = $template->subject;
        $this->editBody    = $template->body;
        $this->showPreview = false;
    }

    /**
     * Save the edited template.
     */
    public function saveTemplate(): void
    {
        $template = NotificationTemplate::findOrFail($this->editingId);

        $this->validate([
            'editSubject' => 'required|string|max:255',
            'editBody'    => 'required|string',
        ]);

        $template->update([
            'subject' => $this->editSubject,
            'body'    => $this->editBody,
        ]);

        Notification::make()
            ->title('Template saved successfully.')
            ->success()
            ->send();
    }

    /**
     * Toggle live preview panel.
     */
    public function togglePreview(): void
    {
        $this->showPreview = !$this->showPreview;
    }

    /**
     * Send a real test email to the currently logged-in admin.
     */
    public function sendTestEmail(): void
    {
        /** @var User $admin */
        $admin = Auth::user();

        if (!$admin) {
            Notification::make()->title('Not authenticated.')->danger()->send();
            return;
        }

        try {
            // Build a temporary template from current editor state
            $template = new NotificationTemplate([
                'key'         => 'test',
                'name'        => 'Test',
                'subject'     => $this->editSubject ?: 'Test Email',
                'body'        => $this->editBody,
                'enabled'     => true,
                'mail_enabled'=> \App\Enums\NotificationEnabledStatus::Force,
                'cc'          => [],
                'bcc'         => [],
            ]);

            $dummyData = [
                'user'    => $admin,
                'invoice' => (object)['id' => 1001, 'number' => 'INV-1001', 'total' => '49.99', 'formattedTotal' => '$49.99', 'due_date' => now()->addDays(14)->format('Y-m-d')],
                'service' => (object)['id' => 1, 'name' => 'Sample Service', 'status' => 'active'],
                'order'   => (object)['id' => 1, 'formattedTotal' => '$49.99'],
                'url'     => '#',
            ];

            NotificationHelper::sendEmailNotification($template, $dummyData, $admin);

            Notification::make()
                ->title('Test email sent to ' . $admin->email)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Failed to send test email: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Discard changes and close the editor.
     */
    public function closeEditor(): void
    {
        $this->editingId   = null;
        $this->editSubject = '';
        $this->editBody    = '';
        $this->showPreview = false;
    }

    /**
     * Render the live email preview HTML.
     * Called by the blade via wire:call or directly.
     */
    public function getPreviewHtmlProperty(): string
    {
        if (!$this->editBody) {
            return '<p style="font-family:sans-serif;color:#888;padding:2rem;text-align:center;">Start typing to see the preview...</p>';
        }

        try {
            $template = new NotificationTemplate([
                'key'     => 'preview',
                'name'    => 'Preview',
                'subject' => $this->editSubject ?: 'Preview',
                'body'    => $this->editBody,
                'enabled' => true,
            ]);

            $dummyData = [
                'user'    => (object)['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'name' => 'John Doe'],
                'invoice' => (object)['id' => 1001, 'number' => 'INV-1001', 'total' => '49.99', 'formattedTotal' => '$49.99', 'due_date' => now()->addDays(14)->format('Y-m-d')],
                'service' => (object)['id' => 1, 'name' => 'Sample Service', 'status' => 'active'],
                'order'   => (object)['id' => 1, 'formattedTotal' => '$49.99'],
                'url'     => '#',
                'items'   => collect([]),
                'total'   => '$49.99',
            ];

            $mailable = new PaymenterMail($template, $dummyData);
            return $mailable->render();
        } catch (\Throwable $e) {
            return '<p style="font-family:sans-serif;color:#dc2626;padding:2rem;"><strong>Preview error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
