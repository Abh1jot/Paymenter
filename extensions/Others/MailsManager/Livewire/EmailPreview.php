<?php

namespace Paymenter\Extensions\Others\MailsManager\Livewire;

use App\Mail\Mail as PaymenterMail;
use App\Models\NotificationTemplate;
use Livewire\Component;

class EmailPreview extends Component
{
    public int $templateId = 0;
    public string $subject = '';
    public string $body = '';

    /**
     * Render the email HTML using Paymenter's real mail template.
     * Returns the rendered HTML string for the preview iframe.
     */
    public function getRenderedHtmlProperty(): string
    {
        if (!$this->body && !$this->templateId) {
            return '<p style="font-family:sans-serif;color:#888;padding:2rem;">Start editing to see a preview...</p>';
        }

        try {
            // Build a temporary template with the current editor content
            $template = new NotificationTemplate([
                'key'     => 'preview',
                'name'    => 'Preview',
                'subject' => $this->subject ?: 'Email Preview',
                'body'    => $this->body,
                'enabled' => true,
            ]);

            // Sample data so Blade variables don't crash
            $dummyData = $this->getDummyData();

            $mailable = new PaymenterMail($template, $dummyData);
            return $mailable->render();
        } catch (\Throwable $e) {
            return '<p style="font-family:sans-serif;color:#dc2626;padding:2rem;"><strong>Preview error:</strong> ' . e($e->getMessage()) . '</p>';
        }
    }

    /**
     * Sample data so variables like {{ $user->first_name }} render in the preview.
     */
    private function getDummyData(): array
    {
        return [
            'user'       => (object) [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'email'      => 'john@example.com',
                'name'       => 'John Doe',
            ],
            'invoice'    => (object) [
                'id'            => 1001,
                'number'        => 'INV-1001',
                'total'         => '49.99',
                'formattedTotal'=> '$49.99',
                'due_date'      => now()->addDays(14)->format('Y-m-d'),
            ],
            'service'    => (object) [
                'id'     => 1,
                'name'   => 'Sample Service',
                'status' => 'active',
            ],
            'order'      => (object) [
                'id'            => 1,
                'formattedTotal'=> '$49.99',
            ],
            'url'        => '#',
        ];
    }

    public function render()
    {
        return view('mailsmanager::livewire.email-preview');
    }
}
