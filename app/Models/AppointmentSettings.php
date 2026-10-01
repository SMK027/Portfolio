<?php

namespace App\Models;

/** Réglages de la prise de rendez-vous (stockés dans « settings »). */
final class AppointmentSettings
{
    public const KEY = 'appointments.settings';

    public const DEFAULTS = [
        'duration'     => 30,
        'notice_hours' => 24,
        'horizon_days' => 30,
        'location'     => 'Visioconférence — le lien vous sera envoyé à la confirmation.',
        'topics'       => ['Stage', 'Alternance', 'Emploi', 'Projet', 'Autre'],
    ];

    /** @return array{duration: int, notice_hours: int, horizon_days: int, location: string, topics: list<string>} */
    public static function current(): array
    {
        return array_merge(self::DEFAULTS, (array) Setting::get(self::KEY, []));
    }

    public static function save(array $values): void
    {
        Setting::set(self::KEY, array_merge(self::current(), $values));
    }
}
