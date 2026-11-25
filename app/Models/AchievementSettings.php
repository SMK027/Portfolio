<?php

namespace App\Models;

/** Réglages du système de succès (stockés dans « settings »). */
final class AchievementSettings
{
    public const KEY = 'achievements.settings';

    public const DEFAULTS = [
        'enabled' => true,
        'title'   => 'Succès',
        'intro'   => 'Explorez le portfolio pour débloquer des succès. Certains sont bien cachés…',
    ];

    /** @return array{enabled: bool, title: string, intro: string} */
    public static function current(): array
    {
        return array_merge(self::DEFAULTS, (array) Setting::get(self::KEY, []));
    }

    public static function enabled(): bool
    {
        return (bool) self::current()['enabled'];
    }

    public static function save(array $values): void
    {
        Setting::set(self::KEY, array_merge(self::current(), $values));
    }
}
