<?php

namespace App\Support;

/**
 * Répare le « mojibake » : texte UTF-8 relu à tort en Windows-1252 puis
 * réenregistré en UTF-8 (« Ã© » au lieu de « é », « â€™ » au lieu de « ’ »).
 *
 * La réparation est faite séquence par séquence : seules les suites de
 * caractères qui reforment un caractère UTF-8 valide sont remplacées, les
 * accents corrects d'un même texte ne sont donc jamais altérés.
 */
final class Mojibake
{
    /** Caractères Windows-1252 des octets 0x80–0x9F. */
    private const CP1252 = [
        "\u{20AC}" => 0x80, "\u{201A}" => 0x82, "\u{0192}" => 0x83, "\u{201E}" => 0x84, "\u{2026}" => 0x85,
        "\u{2020}" => 0x86, "\u{2021}" => 0x87, "\u{02C6}" => 0x88, "\u{2030}" => 0x89, "\u{0160}" => 0x8A,
        "\u{2039}" => 0x8B, "\u{0152}" => 0x8C, "\u{017D}" => 0x8E, "\u{2018}" => 0x91, "\u{2019}" => 0x92,
        "\u{201C}" => 0x93, "\u{201D}" => 0x94, "\u{2022}" => 0x95, "\u{2013}" => 0x96, "\u{2014}" => 0x97,
        "\u{02DC}" => 0x98, "\u{2122}" => 0x99, "\u{0161}" => 0x9A, "\u{203A}" => 0x9B, "\u{0153}" => 0x9C,
        "\u{017E}" => 0x9E, "\u{0178}" => 0x9F,
    ];

    /** Indice rapide : le texte contient-il une séquence typique ? */
    public static function suspect(string $text): bool
    {
        return (bool) preg_match('/[\x{00C2}-\x{00F4}][\x{0080}-\x{00BF}\x{0152}\x{0153}\x{0160}\x{0161}\x{0178}\x{017D}\x{017E}\x{0192}\x{02C6}\x{02DC}\x{2013}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}]/u', $text);
    }

    public static function fix(string $text): string
    {
        // « à » abîmé = « Ã » + espace insécable, souvent devenue une espace normale en chemin.
        // Seulement si le texte contient d'autres séquences abîmées (signal fiable) :
        // - « Ã » + deux espaces : l'une était l'insécable, elle est retirée ;
        // - « Ã » + une espace ou ponctuation (espaces fusionnées) : l'espace est conservée.
        if (self::suspect($text)) {
            $text = preg_replace('/\x{00C3} (?=[ \t])/u', 'à', $text);
            $text = preg_replace('/\x{00C3}(?=[ \t\n\r.,;:!?)»]|$)/u', 'à', $text);
        }

        // Jusqu'à 3 passes : un texte peut avoir été mal converti plusieurs fois.
        for ($pass = 0; $pass < 3 && self::suspect($text); $pass++) {
            $fixed = self::fixOnce($text);
            if ($fixed === $text) {
                break;
            }
            $text = $fixed;
        }

        return $text;
    }

    /** Répare récursivement toutes les chaînes d'un tableau (contenu Editor.js…). */
    public static function fixDeep(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::fix($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => self::fixDeep($item), $value);
        }

        return $value;
    }

    private static function fixOnce(string $text): string
    {
        $chars = mb_str_split($text);
        $count = count($chars);
        $out = '';

        for ($i = 0; $i < $count; $i++) {
            $lead = self::byte($chars[$i]);
            $length = match (true) {
                $lead >= 0xC2 && $lead <= 0xDF => 2,
                $lead >= 0xE0 && $lead <= 0xEF => 3,
                $lead >= 0xF0 && $lead <= 0xF4 => 4,
                default                        => 0,
            };

            if ($length && $i + $length <= $count) {
                $bytes = chr($lead);
                for ($j = 1; $j < $length; $j++) {
                    $next = self::byte($chars[$i + $j]);
                    if ($next === null || $next < 0x80 || $next > 0xBF) {
                        $bytes = null;
                        break;
                    }
                    $bytes .= chr($next);
                }

                if ($bytes !== null && mb_check_encoding($bytes, 'UTF-8')) {
                    $out .= $bytes;
                    $i += $length - 1;

                    continue;
                }
            }

            $out .= $chars[$i];
        }

        return $out;
    }

    /** Octet Windows-1252 correspondant au caractère (ou null). */
    private static function byte(string $char): ?int
    {
        if (isset(self::CP1252[$char])) {
            return self::CP1252[$char];
        }

        $code = mb_ord($char, 'UTF-8');

        // Latin-1 (0xA0–0xFF) et caractères de contrôle C1 (octets non définis en Windows-1252)
        return $code !== false && $code >= 0x80 && $code <= 0xFF ? $code : null;
    }
}
