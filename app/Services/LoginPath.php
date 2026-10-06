<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Adresse de la page de connexion (« /login » par défaut), modifiable depuis le panel.
 *
 * Elle préfixe toutes les connexions : mot de passe, clé de sécurité (« …/cle ») et
 * bots (« …/bot »). L'API, authentifiée par code d'application, n'est pas concernée.
 * Une fois l'adresse personnalisée, l'ancienne répond 404, les liens « Espace
 * administrateur » disparaissent et les invités qui ouvrent une page protégée
 * reçoivent une 404 au lieu d'être redirigés vers la connexion.
 *
 * Adresse oubliée : php artisan portfolio:login-path --reset
 */
class LoginPath
{
    public const SETTING = 'auth.login_path';

    public const DEFAULT = 'login';

    /** Segments en minuscules, chiffres et tirets, séparés par « / ». */
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/';

    /** Premiers segments réservés en plus de ceux des routes et des fichiers de public/. */
    protected const RESERVED = ['api', 'storage', 'build', 'up', 'vendor'];

    /**
     * Adresse enregistrée en base, lue à l'enregistrement des routes. Lecture directe
     * (sans le cache statique de Setting) : la base peut ne pas exister encore.
     */
    public static function configured(): string
    {
        try {
            $value = Setting::query()->find(self::SETTING)?->value;
        } catch (Throwable) {
            return self::DEFAULT;
        }

        return is_string($value) && preg_match(self::PATTERN, $value) ? $value : self::DEFAULT;
    }

    /** Adresse réellement servie (routes en cache comprises). */
    public static function current(): string
    {
        return Route::getRoutes()->getByName('login')?->uri() ?? self::DEFAULT;
    }

    public static function isCustom(): bool
    {
        return self::current() !== self::DEFAULT;
    }

    /** Nettoie une saisie : « /Mon-Acces/ » → « mon-acces ». */
    public static function normalize(?string $path): string
    {
        return strtolower(trim((string) $path, " /\t\n\r\0\x0B"));
    }

    /** Raison du refus d'une adresse, ou null si elle est utilisable. */
    public static function problem(string $path): ?string
    {
        if ($path === self::DEFAULT) {
            return null;
        }
        if (strlen($path) < 4 || strlen($path) > 64) {
            return 'L\'adresse doit contenir entre 4 et 64 caractères.';
        }
        if (! preg_match(self::PATTERN, $path)) {
            return 'Utilisez uniquement des lettres minuscules sans accent, des chiffres, des tirets et des « / ».';
        }
        if (in_array(explode('/', $path)[0], self::reservedSegments(), true)) {
            return 'Cette adresse est déjà utilisée par le site : choisissez-en une autre.';
        }

        return null;
    }

    /** Enregistre l'adresse (« login » = adresse par défaut) et met à jour les routes en cache. */
    public static function set(string $path): void
    {
        Setting::set(self::SETTING, $path === self::DEFAULT ? null : $path);

        if (app()->routesAreCached()) {
            Artisan::call('route:cache');
        }
    }

    /** Premiers segments des routes existantes (hors connexion) et des fichiers publics. */
    protected static function reservedSegments(): array
    {
        $segments = self::RESERVED;
        $current = self::current();

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $current || str_starts_with($route->uri(), $current.'/')) {
                continue;
            }
            $first = explode('/', $route->uri())[0];
            if ($first !== '' && ! str_starts_with($first, '{')) {
                $segments[] = strtolower($first);
            }
        }

        foreach (scandir(public_path()) ?: [] as $entry) {
            $segments[] = strtolower($entry);
        }

        return array_values(array_unique($segments));
    }
}
