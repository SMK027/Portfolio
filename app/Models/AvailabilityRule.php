<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Plage de disponibilité hebdomadaire (ex. : mardi 14:00–17:00). */
class AvailabilityRule extends Model
{
    use Auditable;

    public const WEEKDAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    protected $fillable = ['weekday', 'start_time', 'end_time'];

    public function auditLabel(): string
    {
        return (self::WEEKDAYS[$this->weekday] ?? '?').' '.substr($this->start_time, 0, 5).'–'.substr($this->end_time, 0, 5);
    }
}
