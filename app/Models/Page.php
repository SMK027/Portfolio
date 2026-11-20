<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAccessPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'intro', 'status', 'position'])]
class Page extends Model
{
    use Auditable, HasAccessPassword;

    public const PUBLIC = 'public';
    public const PASSWORD = 'password';
    public const UNLISTED = 'unlisted';
    public const MAINTENANCE = 'maintenance';
    public const DRAFT = 'draft';
    public const PRIVATE = 'private';

    public const STATUSES = [
        self::PUBLIC      => 'Publique',
        self::PASSWORD    => 'Protégée par mot de passe',
        self::UNLISTED    => 'Non indexée (lien direct)',
        self::MAINTENANCE => 'En maintenance',
        self::DRAFT       => 'Brouillon',
        self::PRIVATE     => 'Privée',
    ];

    /** Statuts affichés dans le menu pour les visiteurs. */
    public const LISTED = [self::PUBLIC, self::PASSWORD];

    protected $hidden = ['password'];

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
        'rendez-vous'    => 'appointments.show',
        'recommandations' => 'recommendations.index',
        'machine-ssh'    => 'sandbox.show',
    ];

    /**
     * Menu du site public : groupes thématiques (sous-menus) et liens directs.
     * Un groupe dont une seule page est visible s'affiche comme un lien simple.
     */
    public const NAV_GROUPS = [
        'Parcours' => ['formations', 'experiences', 'diplomes', 'certifications', 'competences', 'recommandations', 'loisirs'],
        'Projets'  => ['projets', 'machine-ssh'],
        'Veille'   => ['veille'],
        'Contact'  => ['contact', 'rendez-vous'],
    ];

    /**
     * Pages visibles regroupées pour le menu : [libellé => Collection<Page>].
     * Les pages absentes des groupes sont ajoutées à la fin, en lien direct.
     */
    public static function navigationFor(?User $user): array
    {
        $pages = static::visibleTo($user)->where('key', '!=', 'home')->keyBy('key');
        $groups = [];
        foreach (self::NAV_GROUPS as $label => $keys) {
            $items = collect($keys)->map(fn ($key) => $pages->pull($key))->filter()->values();
            if ($items->isNotEmpty()) {
                $groups[$label] = $items;
            }
        }
        foreach ($pages as $page) {
            $groups[$page->title] = collect([$page]);
        }

        return $groups;
    }

    /** Cache des pages pour la durée de la requête. */
    protected static ?Collection $cache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
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
     * Pages du menu pour l'utilisateur courant (toutes pour un administrateur) :
     * publiques et protégées par mot de passe.
     *
     * @return Collection<int, Page>
     */
    public static function visibleTo(?User $user): Collection
    {
        return static::allOrdered()->filter(fn (Page $page) => $page->isListedFor($user))->values();
    }

    public function isListedFor(?User $user): bool
    {
        return in_array($this->status, self::LISTED, true) || (bool) $user?->isAdmin();
    }

    public static function isKeyAccessibleBy(string $key, ?User $user): bool
    {
        return (bool) static::findByKey($key)?->isAccessibleBy($user);
    }

    /** Accès au contenu : publique, non indexée, ou protégée et déverrouillée (administrateurs : toujours). */
    public function isAccessibleBy(?User $user): bool
    {
        return in_array($this->status, [self::PUBLIC, self::UNLISTED], true)
            || ($this->status === self::PASSWORD && $this->isUnlocked())
            || (bool) $user?->isAdmin();
    }

    /** Publique au sens strict : menu, recherche, plan du site, indexation. */
    public function getIsPublicAttribute(): bool
    {
        return $this->status === self::PUBLIC;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
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
