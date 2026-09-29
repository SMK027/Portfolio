<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'category', 'level', 'description', 'position'])]
class Skill extends Model
{
    /** Niveau maximal d'une compétence (affiché sous forme de jauge). */
    public const MAX_LEVEL = 5;

    public const LEVEL_LABELS = [
        1 => 'Notions',
        2 => 'Débutant',
        3 => 'Intermédiaire',
        4 => 'Avancé',
        5 => 'Expert',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    public function levelLabel(): ?string
    {
        return self::LEVEL_LABELS[$this->level] ?? null;
    }
}
