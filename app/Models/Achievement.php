<?php

namespace App\Models;

use App\Achievements\Triggers;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Succès que les visiteurs débloquent ; le déclencheur est codé (App\Achievements\Triggers). */
#[Fillable(['title', 'description', 'icon', 'category', 'trigger', 'threshold', 'is_secret', 'is_active', 'position'])]
class Achievement extends Model
{
    use Auditable;

    /** Icônes proposées (composant x-icon). */
    public const ICONS = [
        'trophy', 'sparkles', 'badge', 'heart', 'globe', 'home', 'folder', 'briefcase', 'newspaper', 'academic-cap',
        'search', 'squares', 'moon', 'sun', 'mail', 'calendar', 'download', 'terminal', 'lock', 'clock', 'eye', 'tag', 'users',
    ];

    protected function casts(): array
    {
        return ['threshold' => 'integer', 'is_secret' => 'boolean', 'is_active' => 'boolean', 'position' => 'integer'];
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(AchievementUnlock::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function triggerLabel(): string
    {
        return Triggers::label($this->trigger);
    }

    public function categoryLabel(): string
    {
        return Triggers::CATEGORIES[$this->category][0] ?? $this->category;
    }

    public function auditLabel(): string
    {
        return $this->title;
    }
}
