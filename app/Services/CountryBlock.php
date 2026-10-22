<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Liste noire de pays : les requêtes dont l'IP est localisée (base GeoIP locale,
 * ou en-tête CF-IPCountry d'un proxy) dans un pays bloqué sont refusées sur tout
 * le site, API comprise. Limite le trafic de VPN, bots et attaques automatisées.
 * Une IP non localisée (privée, inconnue, base absente) n'est jamais bloquée,
 * pas plus qu'une adresse de la liste blanche (config auth.login_ban.whitelist).
 */
class CountryBlock
{
    public const KEY = 'security.blocked_countries';

    public function __construct(protected GeoIp $geoip, protected LoginBan $bans)
    {
    }

    /** @return list<string> Codes ISO des pays bloqués (ex. ["CN", "RU"]). */
    public static function countries(): array
    {
        return array_values(array_filter((array) Setting::get(self::KEY, []), fn ($c) => is_string($c) && preg_match('/^[A-Z]{2}$/', $c)));
    }

    /** @param iterable<string> $codes */
    public static function set(iterable $codes): void
    {
        $codes = collect($codes)->map(fn ($c) => strtoupper(trim((string) $c)))
            ->filter(fn ($c) => preg_match('/^[A-Z]{2}$/', $c))->unique()->sort()->values()->all();

        Setting::set(self::KEY, $codes);
    }

    /** Pays de la requête. */
    public function countryOf(Request $request): ?string
    {
        return $this->geoip->country($request->ip(), $request->header('CF-IPCountry'));
    }

    /** Code du pays bloqué d'où provient la requête, ou null si elle est autorisée. */
    public function blockedCountryFor(Request $request): ?string
    {
        $blocked = static::countries();
        if (! $blocked || ! $request->ip() || $this->bans->isWhitelisted($request->ip())) {
            return null;
        }

        $country = $this->countryOf($request);

        return $country && in_array($country, $blocked, true) ? $country : null;
    }

    /** @return array<string, string> Tous les pays (code ISO => libellé avec drapeau), triés par nom. */
    public static function available(): array
    {
        $codes = [];
        try {
            $bundle = \ResourceBundle::create('fr', 'ICUDATA-region');
            foreach ($bundle?->get('Countries') ?? [] as $code => $name) {
                if (preg_match('/^[A-Z]{2}$/', (string) $code) && ! in_array($code, ['EU', 'EZ', 'UN', 'ZZ', 'QO', 'XA', 'XB'], true)) {
                    $codes[$code] = GeoIp::label($code);
                }
            }
        } catch (\Throwable) {
            // intl indisponible : seuls les pays déjà bloqués sont listés.
        }
        foreach (static::countries() as $code) {
            $codes[$code] ??= GeoIp::label($code);
        }

        $collator = class_exists(\Collator::class) ? new \Collator('fr') : null;
        uasort($codes, fn ($a, $b) => $collator ? $collator->compare(mb_substr($a, 3), mb_substr($b, 3)) : strcmp($a, $b));

        return $codes;
    }
}
