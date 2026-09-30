<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Date à précision variable : année seule, mois et année, ou date complète.
 * La date est stockée au premier jour de la période ; la précision indique
 * comment l'afficher et jusqu'à quand la période court.
 */
final class PreciseDate
{
    public const YEAR = 'year';

    public const MONTH = 'month';

    public const DAY = 'day';

    public const LABELS = [
        self::YEAR  => 'Année',
        self::MONTH => 'Mois et année',
        self::DAY   => 'Date complète',
    ];

    /** Format attendu dans les champs de formulaire, selon la précision. */
    public const INPUT_FORMATS = [
        self::YEAR  => 'Y',
        self::MONTH => 'Y-m',
        self::DAY   => 'Y-m-d',
    ];

    /** Règle de validation d'une valeur saisie. */
    public static function rule(string $precision): string
    {
        return match ($precision) {
            self::YEAR  => 'regex:/^(19|20)\d{2}$/',
            self::MONTH => 'date_format:Y-m',
            default     => 'date_format:Y-m-d',
        };
    }

    /** Convertit une saisie en date (premier jour de la période). */
    public static function parse(?string $value, string $precision): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return match ($precision) {
            self::YEAR  => Carbon::create((int) $value, 1, 1),
            self::MONTH => Carbon::createFromFormat('!Y-m', $value),
            default     => Carbon::createFromFormat('!Y-m-d', $value),
        };
    }

    public static function format(?Carbon $date, string $precision, bool $short = false): ?string
    {
        if (! $date) {
            return null;
        }

        return match ($precision) {
            self::YEAR  => $date->format('Y'),
            self::MONTH => $short ? $date->format('m/Y') : $date->translatedFormat('F Y'),
            default     => $short ? $date->format('d/m/Y') : $date->translatedFormat('j F Y'),
        };
    }

    /** Valeur à placer dans le champ de formulaire correspondant à la précision. */
    public static function inputValue(?Carbon $date, string $precision): ?string
    {
        return $date?->format(self::INPUT_FORMATS[$precision] ?? 'Y-m-d');
    }

    /** Dernier instant de la période (ex. : « 2027 » court jusqu'au 31/12/2027). */
    public static function periodEnd(Carbon $date, string $precision): Carbon
    {
        return match ($precision) {
            self::YEAR  => $date->copy()->endOfYear(),
            self::MONTH => $date->copy()->endOfMonth(),
            default     => $date->copy()->endOfDay(),
        };
    }
}
