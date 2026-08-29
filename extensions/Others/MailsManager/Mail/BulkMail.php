<?php

namespace Paymenter\Extensions\Others\MailsManager\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\View\Compilers\BladeCompiler;
use Paymenter\Extensions\Others\MailsManager\Models\BulkCampaign;

class BulkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BulkCampaign $campaign,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
        );
    }

    public function content(): Content
    {
        // Personalise the body for this specific recipient
        $body = $this->personalise($this->campaign->body, $this->recipient);

        // Use Paymenter's base mail template — exactly like App\Mail\Mail does
        return new Content(
            html: 'components.mail.base',
            with: [
                'body'    => $body,
                'subject' => $this->campaign->subject,
                'user'    => $this->recipient,
            ],
        );
    }

    /**
     * Replace {{ $user->xxx }} placeholders with actual recipient values.
     * Uses str_replace so there is no Blade compilation — safe for queue workers.
     */
    private function personalise(string $body, User $user): string
    {
        $fullName = trim($user->first_name . ' ' . $user->last_name);

        $search = [
            '{{ $user->first_name }}', '{{$user->first_name}}',
            '{{ $user->last_name }}',  '{{$user->last_name}}',
            '{{ $user->email }}',      '{{$user->email}}',
            '{{ $user->name }}',       '{{$user->name}}',
        ];

        $replace = [
            $user->first_name, $user->first_name,
            $user->last_name,  $user->last_name,
            $user->email,      $user->email,
            $fullName,         $fullName,
        ];

        return str_replace($search, $replace, $body);
    }
}
