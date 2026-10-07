<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/** Superviseur : identifiant + PIN, rattaché à un administrateur, liste d'opérations débloquables. */
class Supervisor extends Model
{
    use Auditable;

    protected $fillable = ['username', 'user_id', 'permissions', 'is_active'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setPin(string $pin): void
    {
        $this->pin_hash = Hash::make($pin);
    }

    public function checkPin(string $pin): bool
    {
        return Hash::check($pin, $this->pin_hash);
    }

    /** Actif, et rattaché à un administrateur actif. */
    public function isUsable(): bool
    {
        return $this->is_active && $this->user && $this->user->isAdmin() && $this->user->isActive();
    }

    /** Opérations de la liste qu'il peut débloquer. @param list<string> $operations */
    public function grantable(array $operations): array
    {
        return array_values(array_intersect($operations, $this->permissions ?? []));
    }

    public function auditLabel(): string
    {
        return $this->username.($this->user ? ' ('.$this->user->name.')' : '');
    }
}
