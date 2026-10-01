<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Disponibilité ponctuelle (ex. : samedi 12 octobre, 10:00–12:00). */
class AvailabilitySlot extends Model
{
    use Auditable;

    protected $fillable = ['starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function auditLabel(): string
    {
        return $this->starts_at->format('d/m/Y H:i').'–'.$this->ends_at->format('H:i');
    }
}
