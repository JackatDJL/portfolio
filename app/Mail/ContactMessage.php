<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ContactMessage extends Mailable
{
    public function __construct(
        public string $topic,
        public string $senderName,
        public string $senderEmail,
        public string $messageText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail)],
            subject: 'Kontakt: '.$this->topic,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact');
    }
}
