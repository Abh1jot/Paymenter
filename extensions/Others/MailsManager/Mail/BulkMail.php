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

    /**
     * Public $data property — Mailable exposes public properties to the view,
     * so base.blade.php can call BladeCompiler::render($body, $data).
     * This must be named exactly 'data' to match what components.mail.base expects.
     */
    public array $data = [];

    public function __construct(
        public readonly BulkCampaign $campaign,
        public readonly User $recipient,
    ) {
        // Personalise the body for this recipient
        $body = $this->personalise($campaign->body, $recipient);

        // $data mirrors what App\Mail\Mail exposes — the full variable bag
        $this->data = [
            'body'    => $body,
            'subject' => $campaign->subject,
            'user'    => $recipient,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
        );
    }

    /**
     * Use Paymenter's own base mail template.
     * components.mail.base does:
     *   {!! BladeCompiler::render($body, $data) !!}
     *
     * Laravel exposes public Mailable properties as view variables,
     * so $body comes from $data['body'] via 'with', and $data comes
     * from $this->data (the public property).
     */
    public function content(): Content
    {
        return new Content(
            html: 'components.mail.base',
            with: $this->data,
        );
    }

    /**
     * Replace {{ $user->xxx }} placeholders with actual recipient values.
     */
    private function personalise(string $body, User $user): string
    {
        $fullName = trim($user->first_name . ' ' . $user->last_name);

        return str_replace(
            [
                '{{ $user->first_name }}', '{{$user->first_name}}',
                '{{ $user->last_name }}',  '{{$user->last_name}}',
                '{{ $user->email }}',      '{{$user->email}}',
                '{{ $user->name }}',       '{{$user->name}}',
            ],
            [
                $user->first_name, $user->first_name,
                $user->last_name,  $user->last_name,
                $user->email,      $user->email,
                $fullName,         $fullName,
            ],
            $body
        );
    }
}
