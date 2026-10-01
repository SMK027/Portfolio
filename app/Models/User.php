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
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
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
            'two_factor_secret'         => 'encrypted',
            'two_factor_confirmed_at'   => 'datetime',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /* ---------- Authentification à deux facteurs (comptes humains) ---------- */

    public function securityKeys(): HasMany
    {
        return $this->hasMany(SecurityKey::class)->orderBy('created_at');
    }

    public function hasTotp(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    public function hasTwoFactor(): bool
    {
        return ! $this->isMachine() && ($this->hasTotp() || $this->securityKeys()->exists());
    }

    /** Vérifie un code d'application (usage unique : le pas de temps est mémorisé). */
    public function verifyTotp(string $code): bool
    {
        if (! $this->hasTotp()) {
            return false;
        }

        $step = \App\Support\Totp::verify($this->two_factor_secret, $code, $this->two_factor_last_step);
        if ($step === null) {
            return false;
        }

        $this->forceFill(['two_factor_last_step' => $step])->saveQuietly();

        return true;
    }

    /** Génère 8 codes de secours ; seules leurs empreintes sont conservées. @return list<string> */
    public function generateRecoveryCodes(): array
    {
        $codes = collect(range(1, 8))->map(fn () => strtolower(\Illuminate\Support\Str::random(5).'-'.\Illuminate\Support\Str::random(5)))->all();
        $this->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $codes)])->saveQuietly();

        return $codes;
    }

    public function recoveryCodesLeft(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    /** Utilise un code de secours (supprimé ensuite). */
    public function useRecoveryCode(string $code): bool
    {
        $hash = hash('sha256', strtolower(trim($code)));
        $codes = $this->two_factor_recovery_codes ?? [];
        $index = array_search($hash, $codes, true);
        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();

        return true;
    }

    /** Retire tous les facteurs (désactivation complète ou réinitialisation par un super-admin). */
    public function resetTwoFactor(): void
    {
        $this->securityKeys()->get()->each->delete();
        $this->forceFill([
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null, 'two_factor_recovery_codes' => null,
        ])->saveQuietly();
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

    /** Autorisation API d'un compte de service actif (une ou plusieurs séparées par « | »). */
    public function hasServicePermission(string $permissions): bool
    {
        return $this->isService() && $this->is_active
            && (bool) array_intersect(explode('|', $permissions), $this->permissions ?? []);
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
    /** Comptes pouvant être auteur principal d'un article : humains, bots et comptes de service actifs. */
    public function scopeArticleAuthors(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNotIn('global_role', ['service', 'bot'])->orWhere('is_active', true));
    }

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
