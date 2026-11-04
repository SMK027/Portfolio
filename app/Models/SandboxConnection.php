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

    /**
     * Port SSH ou de gestion : « ouverte » = connexion TCP reçue, AVANT toute authentification
     * (le résultat suivant dit si elle a abouti). Terminal web : session déjà authentifiée.
     */
    public function isTcpOpen(): bool
    {
        return $this->result === 'ouverte' && $this->channel !== 'web';
    }

    public function resultLabel(): string
    {
        return $this->isTcpOpen() ? 'connexion TCP (avant authentification)' : $this->result;
    }

    /** Événements utiles par défaut : ni échecs, ni simples ouvertures TCP. */
    public function scopeMeaningful(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('result', 'not like', 'refusée%')
            ->where(fn ($q) => $q->where('result', '!=', 'ouverte')->orWhere('channel', 'web'));
    }

    public static function prune(): int
    {
        static::whereNotNull('ip_address')->where('connected_at', '<', now()->subMonths(3))->update(['ip_address' => null]);

        return static::where('connected_at', '<', now()->subMonths(13))->delete();
    }
}
