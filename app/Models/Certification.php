<?php

namespace App\Models;

use App\Models\Concerns\HasDatePrecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'issuer', 'issued_at', 'expires_at', 'credential_id',
    'credential_url', 'badge_path', 'description', 'position',
])]
class Certification extends Model
{
    use HasDatePrecision;

    protected function casts(): array
    {
        return [
            'issued_at'  => 'date',
            'expires_at' => 'date',
        ];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByDesc('issued_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->periodEnd($this->expires_at)->isPast();
    }

    public function badgeUrl(): ?string
    {
        return $this->badge_path ? Storage::disk('public')->url($this->badge_path) : null;
    }
}
