<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Jour sans rendez-vous (congés, examens…). */
class AvailabilityClosure extends Model
{
    use Auditable;

    protected $fillable = ['date', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function auditLabel(): string
    {
        return $this->date->format('d/m/Y').($this->reason ? ' — '.$this->reason : '');
    }
}
