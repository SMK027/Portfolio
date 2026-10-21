<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catégorie de compétences : fixe l'intitulé et l'ordre d'affichage des groupes.
 * Les compétences y sont rattachées par leur champ « category » (intitulé).
 */
#[Fillable(['name', 'position'])]
class SkillCategory extends Model
{
    use Auditable;

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'category', 'name');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /** Catégorie de même intitulé (casse ignorée), créée en fin de liste si besoin. */
    public static function findOrCreateByName(string $name): self
    {
        return static::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? static::create(['name' => $name, 'position' => (int) static::max('position') + 1]);
    }
}
