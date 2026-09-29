<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Génère automatiquement un slug unique à partir d'un attribut du modèle
 * (par défaut "title") à la création et lorsque cet attribut change.
 */
trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::saving(function ($model) {
            $source = $model->slugSource();

            if (! $model->slug || $model->isDirty($source)) {
                $model->slug = $model->uniqueSlug((string) $model->{$source});
            }
        });
    }

    protected function slugSource(): string
    {
        return 'title';
    }

    protected function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'element';
        $base = Str::limit($base, 100, '');
        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
