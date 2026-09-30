<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Code d'application d'un compte de service.
 * Seule l'empreinte SHA-256 est conservée : le code n'est affiché qu'à sa création.
 */
#[Fillable(['user_id', 'name', 'token_hash', 'token_prefix', 'created_by', 'last_used_at', 'last_used_ip', 'revoked_at'])]
#[Hidden(['token_hash'])]
class ServiceToken extends Model
{
    use Auditable;

    public const PREFIX = 'pfs_';

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    /**
     * Crée un code ; retourne [modèle, code en clair (à afficher une seule fois)].
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(User $account, string $name, ?User $creator = null): array
    {
        $plain = self::PREFIX.Str::random(48);

        $token = self::create([
            'user_id'      => $account->id,
            'name'         => $name,
            'token_hash'   => hash('sha256', $plain),
            'token_prefix' => substr($plain, 0, 12),
            'created_by'   => $creator?->id,
        ]);

        return [$token, $plain];
    }

    /** Code valide (non révoqué) correspondant au code en clair. */
    public static function findValid(string $plain): ?self
    {
        if (! str_starts_with($plain, self::PREFIX) || strlen($plain) > 100) {
            return null;
        }

        return self::active()->where('token_hash', hash('sha256', $plain))->with('user')->first();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function auditLabel(): string
    {
        return $this->name.' ('.$this->token_prefix.'…) — '.($this->user?->name ?? 'compte supprimé');
    }
}
