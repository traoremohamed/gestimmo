<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data)
    {
    }

    public function envelope(): Envelope
    {
        $cc = array_map(function ($email) {
            if ($email instanceof Address) {
                return $email;
            }

            return is_array($email)
                ? new Address($email['address'], $email['name'] ?? '')
                : new Address($email);
        }, (array) ($this->data['cc'] ?? []));

        return new Envelope(
            from: new Address(config('mail.from.address'), config('rbac.mail.from_name')),
            subject: $this->data['sujet'],
            cc: $cc,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'sendmail.index',
            with: ['data' => $this->data],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
