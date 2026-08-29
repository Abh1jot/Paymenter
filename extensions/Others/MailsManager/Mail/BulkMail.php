<?php

namespace Paymenter\Extensions\Others\MailsManager\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
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
        // Personalise body — replace {{ $user->first_name }} and similar simple vars
        $body = $this->personalise($this->campaign->body, $this->recipient);

        return new Content(
            html: 'components.mail.system',
            with: ['body' => $body],
        );
    }

    /**
     * Basic personalisation: replace common user variables in the body.
     * Supports {{ $user->first_name }}, {{ $user->last_name }}, {{ $user->email }}
     */
    private function personalise(string $body, User $user): string
    {
        $fullName = trim($user->first_name . ' ' . $user->last_name);

        return str_replace(
            [
                '{{ $user->first_name }}',
                '{{$user->first_name}}',
                '{{ $user->last_name }}',
                '{{$user->last_name}}',
                '{{ $user->email }}',
                '{{$user->email}}',
                '{{ $user->name }}',
                '{{$user->name}}',
            ],
            [
                $user->first_name,
                $user->first_name,
                $user->last_name,
                $user->last_name,
                $user->email,
                $user->email,
                $fullName,
                $fullName,
            ],
            $body
        );
    }
}
