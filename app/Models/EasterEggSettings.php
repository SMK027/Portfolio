<?php

namespace App\Models;

/** Réglages de la page d'easter eggs (stockés dans « settings »). */
final class EasterEggSettings
{
    public const KEY = 'easter_eggs.settings';

    public const DEFAULTS = [
        'enabled'        => false,
        'title'          => 'Vous avez trouvé la page secrète',
        'intro'          => 'Bravo ! Certains éléments se cachent encore : à vous de les débusquer.',
        // Accès : lien signé puis session, pendant ce nombre de minutes.
        'access_minutes' => 30,
        // Déclencheurs (vides = désactivés), sur toutes les pages publiques.
        'sequence'       => 'ArrowUp ArrowUp ArrowDown ArrowDown ArrowLeft ArrowRight ArrowLeft ArrowRight b a',
        'word'           => '',
        'click_selector' => '',
        'click_count'    => 7,
    ];

    /** @return array{enabled: bool, title: string, intro: string, access_minutes: int, sequence: string, word: string, click_selector: string, click_count: int} */
    public static function current(): array
    {
        return array_merge(self::DEFAULTS, (array) Setting::get(self::KEY, []));
    }

    public static function save(array $values): void
    {
        Setting::set(self::KEY, array_merge(self::current(), $values));
    }
}
