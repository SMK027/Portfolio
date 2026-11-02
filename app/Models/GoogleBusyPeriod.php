<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Période occupée dans Google Agenda (importée, remplacée à chaque synchronisation). */
class GoogleBusyPeriod extends Model
{
    public $timestamps = false;

    protected $fillable = ['starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
