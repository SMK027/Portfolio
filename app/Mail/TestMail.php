<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail de test envoyé depuis l'administration pour vérifier la configuration SMTP.
 */
class TestMail extends Mailable
{
    use Queueable;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test d\'envoi — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test');
    }
}
