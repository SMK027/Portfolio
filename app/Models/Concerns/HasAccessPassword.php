<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Contenu protégé par mot de passe (colonne `password`, castée en `hashed`) :
 * déverrouillage mémorisé dans la session, lié au mot de passe actuel.
 */
trait HasAccessPassword
{
    public function isUnlocked(): bool
    {
        return $this->password && hash_equals($this->unlockToken(), (string) session($this->unlockKey()));
    }

    /**
     * Vérifie le mot de passe (5 essais par minute et par IP) et déverrouille.
     *
     * @return string|null message d'erreur, null si déverrouillé
     */
    public function attemptUnlock(string $password, ?string $ip): ?string
    {
        $key = 'unlock:'.$this->unlockKey().':'.$ip;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.';
        }
        if (! $this->password || ! Hash::check($password, $this->password)) {
            RateLimiter::hit($key, 60);

            return 'Mot de passe incorrect.';
        }

        RateLimiter::clear($key);
        session()->regenerate();
        session()->put($this->unlockKey(), $this->unlockToken());

        return null;
    }

    protected function unlockKey(): string
    {
        return 'unlocked.'.Str::snake(class_basename($this)).'.'.$this->getKey();
    }

    /** Lié au mot de passe actuel : le changer reverrouille les sessions ouvertes. */
    protected function unlockToken(): string
    {
        return hash_hmac('sha256', (string) $this->password, (string) config('app.key'));
    }
}
