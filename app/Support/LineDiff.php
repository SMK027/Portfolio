<?php

namespace App\Support;

/**
 * Différence ligne à ligne (plus longue sous-séquence commune).
 * Résultat : liste de ['type' => 'same'|'added'|'removed', 'line' => string].
 */
final class LineDiff
{
    /** @return list<array{type: string, line: string}> */
    public static function compare(array $old, array $new): array
    {
        $n = count($old);
        $m = count($new);

        // Contenus très longs : comparaison simplifiée pour rester rapide.
        if ($n * $m > 4_000_000) {
            return [
                ...array_map(fn ($l) => ['type' => 'removed', 'line' => $l], array_values(array_diff($old, $new))),
                ...array_map(fn ($l) => ['type' => 'added', 'line' => $l], array_values(array_diff($new, $old))),
            ];
        }

        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $old[$i] === $new[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $diff = [];
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($old[$i] === $new[$j]) {
                $diff[] = ['type' => 'same', 'line' => $old[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $diff[] = ['type' => 'removed', 'line' => $old[$i++]];
            } else {
                $diff[] = ['type' => 'added', 'line' => $new[$j++]];
            }
        }
        while ($i < $n) {
            $diff[] = ['type' => 'removed', 'line' => $old[$i++]];
        }
        while ($j < $m) {
            $diff[] = ['type' => 'added', 'line' => $new[$j++]];
        }

        return $diff;
    }
}
