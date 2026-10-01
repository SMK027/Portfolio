<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail au propriétaire du site : nouvelle demande ou annulation. */
class AppointmentOwnerMail extends Mailable
{
    public const REQUESTED = 'requested';

    public const CANCELLED = 'cancelled';

    public function __construct(public Appointment $appointment, public string $event)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->appointment->email, $this->appointment->name)],
            subject: ($this->event === self::CANCELLED ? '[Rendez-vous annulé] ' : '[Rendez-vous] ')
                .$this->appointment->starts_at->translatedFormat('j F à H:i').' — '.$this->appointment->name,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.appointment-owner');
    }
}
