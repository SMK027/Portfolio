<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[Fillable(['name', 'category', 'level', 'description', 'position'])]
class Skill extends Model
{
    use Auditable;

    /** Niveau maximal d'une compétence (affiché sous forme de jauge). */
    public const MAX_LEVEL = 5;

    public const LEVEL_LABELS = [
        1 => 'Notions',
        2 => 'Débutant',
        3 => 'Intermédiaire',
        4 => 'Avancé',
        5 => 'Expert',
    ];

    protected static function booted(): void
    {
        // Toute catégorie saisie (formulaire, import…) rejoint la liste gérée,
        // sous l'intitulé existant lorsqu'il ne diffère que par la casse.
        static::saving(function (Skill $skill) {
            $name = trim((string) $skill->category);
            $skill->category = $name === '' ? null : SkillCategory::findOrCreateByName($name)->name;
        });
    }

    /**
     * Regroupe des compétences par catégorie, dans l'ordre des catégories
     * (« Autres » en dernier pour les compétences sans catégorie).
     *
     * @param  Collection<int, Skill>  $skills
     * @return Collection<string, Collection<int, Skill>>
     */
    public static function groupByCategory(Collection $skills): Collection
    {
        $order = SkillCategory::ordered()->pluck('name')->flip();

        return $skills->groupBy(fn (Skill $skill) => $skill->category ?: 'Autres')
            ->sortBy(fn ($group, string $category) => $order[$category] ?? PHP_INT_MAX);
    }

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
