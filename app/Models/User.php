<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'name', 'email', 'password', 'global_role', 'avatar', 'bio'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
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
        ];
    }

    /** Rôles attribuables depuis l'administration. */
    public const ROLES = [
        'superadmin' => 'Super-administrateur',
        'admin'      => 'Administrateur',
        'user'       => 'Contributeur (co-auteur sans accès admin)',
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
        return self::ROLES[$this->global_role] ?? ucfirst((string) $this->global_role);
    }

    public function isAdmin(): bool
    {
        return in_array($this->global_role, ['admin', 'superadmin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->global_role === 'superadmin';
    }
}
