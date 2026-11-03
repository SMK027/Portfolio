<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Connexion à la machine SSH (IP effacée après 3 mois, ligne supprimée après 13 mois). */
#[Fillable(['connected_at', 'ip_address', 'channel', 'username', 'result'])]
class SandboxConnection extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
        ];
    }

    public function isRefused(): bool
    {
        return str_starts_with($this->result, 'refusée');
    }

    public static function prune(): int
    {
        static::whereNotNull('ip_address')->where('connected_at', '<', now()->subMonths(3))->update(['ip_address' => null]);

        return static::where('connected_at', '<', now()->subMonths(13))->delete();
    }
}
