<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;

/**
 * Pays d'une adresse IP, à partir d'une base locale (DB-IP « IP to Country
 * Lite », licence CC BY 4.0) : aucune IP n'est envoyée à un service externe.
 */
class GeoIp
{
    protected ?Reader $reader = null;

    public static function path(): string
    {
        return storage_path('app/geoip/country.mmdb');
    }

    /** Code pays ISO (ex. « FR »), ou null (IP privée, inconnue, base absente). */
    public function country(?string $ip, ?string $header = null): ?string
    {
        // En-tête fourni par un proxy (ex. Cloudflare), si présent et valide.
        if ($header && preg_match('/^[A-Z]{2}$/', $header) && ! in_array($header, ['XX', 'T1'], true)) {
            return $header;
        }
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        try {
            $this->reader ??= is_file(self::path()) ? new Reader(self::path()) : null;
            $code = $this->reader?->get($ip)['country']['iso_code'] ?? null;

            return is_string($code) && strlen($code) === 2 ? strtoupper($code) : null;
        } catch (\Throwable $e) {
            Log::warning('GeoIP : recherche impossible — '.$e->getMessage());

            return null;
        }
    }

    /** Télécharge la base du mois (ou du mois précédent si pas encore publiée). */
    public function update(): bool
    {
        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $url = 'https://download.db-ip.com/free/dbip-country-lite-'.$month->format('Y-m').'.mmdb.gz';
            try {
                $response = Http::timeout(120)->get($url);
                if (! $response->successful()) {
                    continue;
                }
                $data = gzdecode($response->body());
                if ($data === false || strlen($data) < 1_000_000) {
                    continue;
                }
                @mkdir(dirname(self::path()), 0775, true);
                file_put_contents(self::path().'.tmp', $data);
                new Reader(self::path().'.tmp'); // base valide ?
                rename(self::path().'.tmp', self::path());

                return true;
            } catch (\Throwable $e) {
                Log::warning("GeoIP : mise à jour impossible ({$url}) — ".$e->getMessage());
                @unlink(self::path().'.tmp');
            }
        }

        return false;
    }

    /** Nom du pays en français, avec drapeau. */
    public static function label(?string $code): string
    {
        if (! $code) {
            return 'Inconnu';
        }
        $flag = implode('', array_map(fn ($c) => mb_chr(0x1F1E6 + ord($c) - 65), str_split($code)));
        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, 'fr') : $code;

        return $flag.' '.($name ?: $code);
    }
}
