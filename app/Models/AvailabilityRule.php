<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Plage de disponibilité hebdomadaire (ex. : mardi 14:00–17:00), toutes les semaines
 * ou seulement sur une période (starts_on → ends_on, bornes incluses).
 */
class AvailabilityRule extends Model
{
    use Auditable;

    public const WEEKDAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    protected $fillable = ['weekday', 'start_time', 'end_time', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function hasPeriod(): bool
    {
        return $this->starts_on !== null;
    }

    /** La plage s'applique-t-elle ce jour-là (jour de la semaine et période) ? */
    public function appliesOn(CarbonInterface $day): bool
    {
        return $day->isoWeekday() === (int) $this->weekday
            && (! $this->starts_on || $day->toDateString() >= $this->starts_on->toDateString())
            && (! $this->ends_on || $day->toDateString() <= $this->ends_on->toDateString());
    }

    public function periodLabel(): ?string
    {
        return $this->hasPeriod() ? 'du '.$this->starts_on->format('d/m/Y').' au '.$this->ends_on->format('d/m/Y') : null;
    }

    public function auditLabel(): string
    {
        return (self::WEEKDAYS[$this->weekday] ?? '?').' '.substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5)
            .($this->hasPeriod() ? ' ('.$this->periodLabel().')' : '');
    }
}
