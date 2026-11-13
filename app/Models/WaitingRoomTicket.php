<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Ticket d'un visiteur dans la file d'attente (voir App\Services\WaitingRoom). */
#[Fillable(['token', 'admitted_at', 'last_seen_at'])]
class WaitingRoomTicket extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'admitted_at'  => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function isAdmitted(): bool
    {
        return $this->admitted_at !== null;
    }

    public function scopeAdmitted(Builder $query): void
    {
        $query->whereNotNull('admitted_at');
    }

    public function scopeWaiting(Builder $query): void
    {
        $query->whereNull('admitted_at');
    }
}
