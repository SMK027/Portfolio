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
#[Fillable(['ip_address', 'attempts', 'reason', 'banned_until'])]
class IpBan extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'banned_until' => 'datetime',
            'lifted_at'    => 'datetime',
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

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function auditLabel(): string
    {
        return $this->ip_address;
    }
}
