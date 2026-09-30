<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'intro', 'is_public', 'position'])]
class Page extends Model
{
    use Auditable;

    /**
     * Correspondance clé de page → nom de la route publique.
     */
    public const ROUTES = [
        'home'           => 'home',
        'formations'     => 'formations',
        'experiences'    => 'experiences',
        'diplomes'       => 'diplomes',
        'certifications' => 'certifications',
        'competences'    => 'competences',
        'projets'        => 'projects.index',
        'veille'         => 'articles.index',
        'loisirs'        => 'loisirs',
        'contact'        => 'contact.show',
    ];

    /** Cache des pages pour la durée de la requête. */
    protected static ?Collection $cache = null;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public static function flushCache(): void
    {
        static::$cache = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
    }

    /** @return Collection<int, Page> */
    public static function allOrdered(): Collection
    {
        return static::$cache ??= static::query()->orderBy('position')->orderBy('id')->get();
    }

    public static function findByKey(string $key): ?self
    {
        return static::allOrdered()->firstWhere('key', $key);
    }

    /**
     * Pages visibles par l'utilisateur courant (toutes pour un administrateur).
     *
     * @return Collection<int, Page>
     */
    public static function visibleTo(?User $user): Collection
    {
        return static::allOrdered()->filter(fn (Page $page) => $page->isAccessibleBy($user))->values();
    }

    public static function isKeyAccessibleBy(string $key, ?User $user): bool
    {
        return (bool) static::findByKey($key)?->isAccessibleBy($user);
    }

    public function isAccessibleBy(?User $user): bool
    {
        return $this->is_public || (bool) $user?->isAdmin();
    }

    public function routeName(): string
    {
        return self::ROUTES[$this->key];
    }

    public function url(): string
    {
        return route($this->routeName());
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
