<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'name', 'email', 'password', 'global_role', 'permissions', 'description', 'is_active', 'avatar', 'bio'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'permissions'       => 'array',
            'is_active'         => 'boolean',
        ];
    }

    /** Rôles attribuables depuis l'administration. */
    public const ROLES = [
        'superadmin' => 'Super-administrateur',
        'admin'      => 'Administrateur',
        'user'       => 'Contributeur (rédige des articles soumis à validation)',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function coauthoredArticles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_coauthor');
    }

    public function roleLabel(): string
    {
        if ($this->isService()) {
            return 'Compte de service';
        }
        if ($this->isBot()) {
            return 'Bot';
        }

        return self::ROLES[$this->global_role] ?? ucfirst((string) $this->global_role);
    }

    public function isAdmin(): bool
    {
        return in_array($this->global_role, ['admin', 'superadmin']);
    }

    /** Contributeur : accès à la rédaction d'articles uniquement (publication validée par un admin). */
    public function isContributor(): bool
    {
        return $this->global_role === 'user';
    }

    /** Compte de service : accès à l'API uniquement (codes d'application), jamais au panel. */
    public function isService(): bool
    {
        return $this->global_role === 'service';
    }

    /** Bot : connexion au panel par code d'application, avec des autorisations limitées. */
    public function isBot(): bool
    {
        return $this->global_role === 'bot';
    }

    /** Compte technique (service ou bot) : jamais de mot de passe ni de page « Mon compte ». */
    public function isMachine(): bool
    {
        return $this->isService() || $this->isBot();
    }

    /** Autorisation API d'un compte de service actif. */
    public function hasServicePermission(string $permission): bool
    {
        return $this->isService() && $this->is_active && in_array($permission, $this->permissions ?? [], true);
    }

    /** Autorisation d'un bot actif (une ou plusieurs séparées par « | »). */
    public function hasBotPermission(string $permissions): bool
    {
        if (! $this->isBot() || ! $this->is_active) {
            return false;
        }

        return (bool) array_intersect(explode('|', $permissions), $this->permissions ?? []);
    }

    /** Accès à une section du panel : administrateurs, ou bots autorisés. */
    public function canUsePanel(string $permissions): bool
    {
        return $this->isAdmin() || $this->hasBotPermission($permissions);
    }

    public function serviceTokens(): HasMany
    {
        return $this->hasMany(ServiceToken::class)->latest('id');
    }

    /** Comptes humains (hors comptes de service). */
    public function scopeHumans(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNotIn('global_role', ['service', 'bot']);
    }

    /** Accès à l'éditeur d'articles (administrateurs et contributeurs). */
    public function canWriteArticles(): bool
    {
        return $this->isAdmin() || $this->isContributor() || $this->hasBotPermission('articles.read|articles.write');
    }

    public function isSuperAdmin(): bool
    {
        return $this->global_role === 'superadmin';
    }
}
