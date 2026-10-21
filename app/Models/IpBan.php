<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adresse IP bannie de tout le site (voir App\Services\LoginBan).
 */
#[Fillable(['ip_address', 'attempts', 'is_manual', 'reason', 'http_status', 'banned_until'])]
class IpBan extends Model
{
    use Auditable;

    public const DEFAULT_STATUS = 403;

    /** Codes HTTP proposés pour la réponse à une adresse bannie (tous avec un corps de page). */
    public const HTTP_STATUSES = [
        403 => '403 Forbidden (par défaut)',
        200 => '200 OK',
        400 => '400 Bad Request',
        401 => '401 Unauthorized',
        404 => '404 Not Found',
        410 => '410 Gone',
        418 => '418 I\'m a teapot',
        429 => '429 Too Many Requests',
        451 => '451 Unavailable For Legal Reasons',
        500 => '500 Internal Server Error',
        502 => '502 Bad Gateway',
        503 => '503 Service Unavailable',
    ];

    protected function casts(): array
    {
        return [
            'banned_until' => 'datetime',
            'lifted_at'    => 'datetime',
            'is_manual'    => 'boolean',
            'http_status'  => 'integer',
        ];
    }

    /** Bannissements en cours : ni levés, ni expirés. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at')
            ->where(fn ($q) => $q->whereNull('banned_until')->orWhere('banned_until', '>', now()));
    }

    public function isActive(): bool
    {
        return ! $this->lifted_at && (! $this->banned_until || $this->banned_until->isFuture());
    }

    public function status(): int
    {
        return array_key_exists((int) $this->http_status, self::HTTP_STATUSES) ? (int) $this->http_status : self::DEFAULT_STATUS;
    }

    /** Message montré à l'adresse bannie : motif réel pour un bannissement manuel. */
    public function publicMessage(): string
    {
        if ($this->is_manual) {
            return $this->reason
                ? 'Votre adresse IP a été bloquée. Motif : '.$this->reason
                : 'Votre adresse IP a été bloquée par l\'administrateur du site.';
        }

        return 'Votre adresse IP est temporairement bloquée après plusieurs échecs de connexion.';
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function auditLabel(): string
    {
        return $this->ip_address;
    }
}
