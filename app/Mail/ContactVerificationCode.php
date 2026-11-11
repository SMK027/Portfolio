<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactVerificationCode extends Mailable
{
    use Queueable;

    public function __construct(public string $code, public string $firstName, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de vérification : '.$this->code);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-verification');
    }
}
