<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\Profile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail au visiteur : demande reçue, confirmée, refusée, ou annulée par un blocage d'horaire. */
class AppointmentVisitorMail extends Mailable
{
    public const RECEIVED = 'received';

    public const CONFIRMED = 'confirmed';

    public const DECLINED = 'declined';

    /** Créneau bloqué après coup : le visiteur est invité à en réserver un autre. */
    public const RESCHEDULE = 'reschedule';

    /** Rappels d'un rendez-vous confirmé : la veille et 1 h avant. */
    public const REMINDER_DAY = 'reminder_day';

    public const REMINDER_HOUR = 'reminder_hour';

    public function __construct(public Appointment $appointment, public string $event)
    {
    }

    public function envelope(): Envelope
    {
        $when = $this->appointment->starts_at->translatedFormat('j F à H:i');

        return new Envelope(subject: match ($this->event) {
            self::CONFIRMED => 'Rendez-vous confirmé — '.$when,
            self::DECLINED  => 'Demande de rendez-vous du '.$when,
            self::RESCHEDULE => 'Rendez-vous du '.$when.' annulé — merci d\'en choisir un autre',
            self::REMINDER_DAY  => 'Rappel : rendez-vous '.($this->appointment->starts_at->isTomorrow() ? 'demain' : 'le '.$this->appointment->starts_at->translatedFormat('j F')).' à '.$this->appointment->starts_at->format('H:i'),
            self::REMINDER_HOUR => 'Rappel : rendez-vous à '.$this->appointment->starts_at->format('H:i').' (dans moins d\'une heure)',
            default         => 'Demande de rendez-vous reçue — '.$when,
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.appointment-visitor', with: [
            'owner'    => Profile::current()->fullName(),
            'settings' => AppointmentSettings::current(),
        ]);
    }

    /** Invitation agenda jointe à la confirmation et au rappel de la veille. */
    public function attachments(): array
    {
        return in_array($this->event, [self::CONFIRMED, self::REMINDER_DAY], true)
            ? [Attachment::fromData(fn () => $this->appointment->toIcs(Profile::current()->fullName()), 'rendez-vous.ics')->withMime('text/calendar')]
            : [];
    }
}
