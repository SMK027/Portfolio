<?php

namespace App\Achievements;

/**
 * Défis Linux de la machine SSH (sandbox/ssh/defis). La machine est isolée du
 * portfolio : le visiteur valide sa réponse sur la page publique. Les empreintes
 * SHA-256 sont celles calculées par sandbox/ssh/defis/build.sh — à garder identiques.
 */
final class SandboxChallenges
{
    public const ALL = [
        1 => ['Fichier caché', '84168ccccd575c2982868334bbf72e13c5f83cbea88a08bbd81dcf458f2dd5f5'],
        2 => ['Recherche dans un journal', '51abe8af54854e62fa23748cd04c1e11463482fab33834b3604c7b49c47ff966'],
        3 => ['Permissions', 'e0fee4c9a9eddde0ab0cc34cca18bb298b47e042d3654f3abc2385ca39becbc9'],
        4 => ['Labyrinthe', '8548e7d19852495e5a4397ae15fc133e14e4be084f0eecb4eb17388aa09e7d78'],
        5 => ['Visiteurs uniques', '7a61b53701befdae0eeeffaecc73f14e20b537bb0f8b91ad7c2936dc63562b25'],
    ];

    /** Même comparaison que la commande « defis verifier » (réponse exacte, espaces de bord ignorés). */
    public static function check(int $number, string $answer): bool
    {
        return isset(self::ALL[$number]) && hash_equals(self::ALL[$number][1], hash('sha256', trim($answer)));
    }
}
