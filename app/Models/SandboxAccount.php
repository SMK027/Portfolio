<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Compte de la machine SSH bac à sable. Seule l'empreinte SHA-512 (crypt) du mot
 * de passe est envoyée à la machine ; le mot de passe du compte invité est en
 * plus conservé chiffré pour être affiché aux visiteurs.
 */
#[Fillable(['username', 'label', 'is_guest', 'is_enabled', 'expires_at'])]
class SandboxAccount extends Model
{
    use Auditable;

    /** Noms réservés (comptes système de la machine). */
    public const RESERVED = ['root', 'admin', 'bin', 'daemon', 'adm', 'lp', 'sync', 'shutdown', 'halt', 'mail', 'news', 'uucp',
        'operator', 'man', 'postmaster', 'cron', 'ftp', 'sshd', 'at', 'squid', 'xfs', 'games', 'cyrus', 'vpopmail', 'ntp',
        'smmsp', 'nobody', 'sandbox', 'node', 'www-data'];

    public const USERNAME_PATTERN = '/^[a-z][a-z0-9_-]{2,31}$/';

    protected $hidden = ['password_hash', 'password_encrypted'];

    protected function casts(): array
    {
        return [
            'is_guest'   => 'boolean',
            'is_enabled' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public static function generatePassword(): string
    {
        // Lisible à l'écran : sans caractères ambigus
        return collect(range(1, 3))->map(fn () => Str::of(Str::random(12))->replaceMatches('/[0OoIl1]/', '')->substr(0, 4)->padRight(4, 'x'))->join('-');
    }

    /** Définit le mot de passe (empreinte SHA-512 crypt, et version chiffrée pour l'invité). */
    public function setPassword(string $password): void
    {
        $this->password_hash = crypt($password, '$6$rounds=50000$'.Str::random(16).'$');
        $this->password_encrypted = $this->is_guest ? Crypt::encryptString($password) : null;
    }

    /** Mot de passe en clair du compte invité (affiché sur la page publique). */
    public function guestPassword(): ?string
    {
        try {
            return $this->is_guest && $this->password_encrypted ? Crypt::decryptString($this->password_encrypted) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isActive(): bool
    {
        return $this->is_enabled && ! $this->isExpired();
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_guest')->orderBy('username');
    }

    public static function guest(): ?self
    {
        return static::where('is_guest', true)->first();
    }

    public function auditLabel(): string
    {
        return $this->username;
    }
}
