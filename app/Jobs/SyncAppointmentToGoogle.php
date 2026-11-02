<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\GoogleCalendar;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reporte un rendez-vous dans Google Agenda : événement créé ou mis à jour s'il est
 * confirmé, supprimé sinon (refusé, annulé ou rendez-vous supprimé : $appointment null).
 */
class SyncAppointmentToGoogle implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Secondes entre deux tentatives */
    public array $backoff = [60, 300];

    public function __construct(public ?int $appointmentId, public ?string $eventId = null)
    {
    }

    public function handle(GoogleCalendar $google): void
    {
        if (! $google->isConnected()) {
            return;
        }

        $appointment = $this->appointmentId ? Appointment::find($this->appointmentId) : null;

        if ($appointment?->status === 'confirmed') {
            $google->pushAppointment($appointment);
        } elseif ($eventId = $appointment?->google_event_id ?? $this->eventId) {
            $google->deleteEvent($eventId);
            $appointment?->forceFill(['google_event_id' => null])->saveQuietly();
        }
    }
}
