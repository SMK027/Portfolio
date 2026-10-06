<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Demande de rendez-vous d'un visiteur. */
class Appointment extends Model
{
    use Auditable;

    public const STATUSES = [
        'pending'   => 'En attente',
        'confirmed' => 'Confirmé',
        'declined'  => 'Refusé',
        'cancelled' => 'Annulé',
    ];

    /** Statuts qui réservent le créneau. */
    public const HOLDING = ['pending', 'confirmed'];

    protected $fillable = ['starts_at', 'ends_at', 'name', 'email', 'phone', 'topic', 'message', 'status', 'admin_note', 'cancel_token', 'ip_address', 'consented_at'];

    protected $hidden = ['cancel_token'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'consented_at' => 'datetime',
            'day_reminder_sent_at' => 'datetime', 'hour_reminder_sent_at' => 'datetime',
        ];
    }

    public function scopeHolding(Builder $query): void
    {
        $query->whereIn('status', self::HOLDING);
    }

    /** Chevauche la période donnée. */
    public function scopeOverlapping(Builder $query, $start, $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at->isFuture();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, self::HOLDING, true) && $this->isUpcoming();
    }

    public function cancelUrl(): string
    {
        return route('appointments.cancel', $this->cancel_token);
    }

    public function auditLabel(): string
    {
        return $this->starts_at->format('d/m/Y H:i').' — '.$this->name;
    }

    /** Fichier iCalendar (.ics) pour l'ajouter à un agenda. */
    public function toIcs(string $organizer): string
    {
        $esc = fn (string $v) => addcslashes(str_replace(["\r\n", "\n"], '\n', $v), ',;\\');
        $fmt = fn ($d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $settings = AppointmentSettings::current();

        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//'.$esc(config('app.name')).'//Rendez-vous//FR', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:appointment-'.$this->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($this->starts_at),
            'DTEND:'.$fmt($this->ends_at),
            'SUMMARY:'.$esc('Rendez-vous avec '.$organizer.' — '.$this->topic),
            'LOCATION:'.$esc((string) $settings['location']),
            'DESCRIPTION:'.$esc((string) $this->admin_note),
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);
    }
}
