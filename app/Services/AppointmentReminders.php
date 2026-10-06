<?php

namespace App\Services;

use App\Mail\AppointmentVisitorMail;
use App\Models\Appointment;
use App\Models\AppointmentSettings;

/**
 * Rappels envoyés aux visiteurs dont le rendez-vous est confirmé : la veille (24 h avant)
 * et 1 h avant. Tâche planifiée toutes les 5 minutes ; chaque rappel n'est envoyé qu'une fois.
 * Un rendez-vous confirmé tardivement ne reçoit pas de rappel redondant (voir skipCovered()).
 */
class AppointmentReminders
{
    /** Rappels (colonne d'envoi => [heures avant le rendez-vous, réglage, événement e-mail]). */
    public const KINDS = [
        'day_reminder_sent_at'  => [24, 'reminder_day', AppointmentVisitorMail::REMINDER_DAY],
        'hour_reminder_sent_at' => [1, 'reminder_hour', AppointmentVisitorMail::REMINDER_HOUR],
    ];

    public function __construct(protected SafeMailer $mailer)
    {
    }

    /** Envoie les rappels dus ; retourne le nombre d'e-mails confiés à l'envoi. */
    public function send(): int
    {
        $settings = AppointmentSettings::current();
        $sent = 0;

        foreach (self::KINDS as $column => [$hours, $setting, $event]) {
            if (! $settings[$setting]) {
                continue;
            }

            Appointment::where('status', 'confirmed')
                ->whereNull($column)
                ->where('starts_at', '>', now())
                ->where('starts_at', '<=', now()->addHours($hours))
                ->orderBy('starts_at')
                ->get()
                ->each(function (Appointment $appointment) use ($column, $event, &$sent) {
                    // Rappel de la veille inutile si le rendez-vous a lieu dans l'heure : le rappel « 1 h » suffit.
                    if ($column === 'day_reminder_sent_at' && $appointment->starts_at->lte(now()->addHour())) {
                        $appointment->forceFill([$column => now()])->saveQuietly();

                        return;
                    }

                    $appointment->forceFill([$column => now()])->saveQuietly();
                    if ($this->mailer->queue($appointment->email, new AppointmentVisitorMail($appointment, $event), 'rappel de rendez-vous')) {
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    /**
     * Rendez-vous confirmé à moins de 24 h (ou 1 h) de son début : l'e-mail de confirmation
     * tient lieu de rappel, qui n'est donc pas envoyé en plus.
     */
    public static function skipCovered(Appointment $appointment): void
    {
        foreach (self::KINDS as $column => [$hours]) {
            if ($appointment->starts_at->lte(now()->addHours($hours)) && ! $appointment->{$column}) {
                $appointment->forceFill([$column => now()])->saveQuietly();
            }
        }
    }
}
