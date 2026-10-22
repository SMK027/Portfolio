<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Localisation approximative d'une adresse IP (usage statistique uniquement),
 * à partir de bases locales DB-IP « Lite » (licence CC BY 4.0) lues avec
 * geoip2/geoip2 : aucune IP n'est envoyée à un service externe.
 * La base « City » (pays, région, ville) est utilisée si présente, sinon la
 * base « Country ». La précision reste celle de la ville, au mieux.
 */
class GeoIp
{
    public const DATABASES = ['city' => 'dbip-city-lite', 'country' => 'dbip-country-lite'];

    /** @var array<string, Reader|false> */
    protected array $readers = [];

    public static function path(string $database = 'country'): string
    {
        return storage_path("app/geoip/{$database}.mmdb");
    }

    /**
     * @return array{country: ?string, region: ?string, city: ?string, latitude: ?float, longitude: ?float}
     */
    public function locate(?string $ip, ?string $header = null): array
    {
        $location = ['country' => null, 'region' => null, 'city' => null, 'latitude' => null, 'longitude' => null];

        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            try {
                if ($reader = $this->reader('city')) {
                    $record = $reader->city($ip);
                    $location = [
                        'country'   => $record->country->isoCode,
                        'region'    => $this->limit($record->mostSpecificSubdivision->name),
                        'city'      => $this->limit($record->city->name),
                        // Centre de la ville (pas la position du visiteur), arrondi à ~1 km.
                        'latitude'  => $record->location->latitude !== null ? round($record->location->latitude, 2) : null,
                        'longitude' => $record->location->longitude !== null ? round($record->location->longitude, 2) : null,
                    ];
                } elseif ($reader = $this->reader('country')) {
                    $location['country'] = $reader->country($ip)->country->isoCode;
                }
            } catch (AddressNotFoundException) {
                // IP absente de la base : localisation inconnue.
            } catch (\Throwable $e) {
                Log::warning('GeoIP : recherche impossible — '.$e->getMessage());
            }
        }

        // En-tête fourni par un proxy (ex. Cloudflare), prioritaire pour le pays s'il est valide.
        if ($header && preg_match('/^[A-Z]{2}$/', $header) && ! in_array($header, ['XX', 'T1'], true)) {
            if ($location['country'] !== null && $location['country'] !== $header) {
                $location = array_map(fn () => null, $location); // ville incohérente avec le pays
            }
            $location['country'] = $header;
        }

        $code = $location['country'];
        $location['country'] = is_string($code) && strlen($code) === 2 ? strtoupper($code) : null;

        return $location;
    }

    /** Code pays ISO (ex. « FR »), ou null (IP privée, inconnue, base absente). */
    public function country(?string $ip, ?string $header = null): ?string
    {
        return $this->locate($ip, $header)['country'];
    }

    protected function reader(string $database): ?Reader
    {
        if (! isset($this->readers[$database])) {
            $this->readers[$database] = is_file(self::path($database)) ? new Reader(self::path($database), ['fr', 'en']) : false;
        }

        return $this->readers[$database] ?: null;
    }

    protected function limit(?string $value): ?string
    {
        return $value !== null && $value !== '' ? mb_substr($value, 0, 100) : null;
    }

    /** Télécharge les bases du mois (ou du mois précédent si pas encore publiées). */
    public function update(): bool
    {
        $ok = true;
        foreach (self::DATABASES as $database => $name) {
            $ok = $this->download($database, $name) && $ok;
        }

        return $ok;
    }

    protected function download(string $database, string $name): bool
    {
        $path = self::path($database);
        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $url = "https://download.db-ip.com/free/{$name}-".$month->format('Y-m').'.mmdb.gz';
            try {
                // Téléchargement et décompression en flux : la base « City » dépasse 100 Mo.
                @mkdir(dirname($path), 0775, true);
                $response = Http::timeout(600)->sink($path.'.gz')->get($url);
                if (! $response->successful() || ! $this->gunzip($path.'.gz', $path.'.tmp') || filesize($path.'.tmp') < 1_000_000) {
                    @unlink($path.'.tmp');

                    continue;
                }
                (new Reader($path.'.tmp'))->close(); // base valide ?
                rename($path.'.tmp', $path);
                unset($this->readers[$database]);

                return true;
            } catch (\Throwable $e) {
                Log::warning("GeoIP : mise à jour impossible ({$url}) — ".$e->getMessage());
                @unlink($path.'.tmp');
            } finally {
                @unlink($path.'.gz');
            }
        }

        return false;
    }

    protected function gunzip(string $from, string $to): bool
    {
        $in = @gzopen($from, 'rb');
        $out = @fopen($to, 'wb');
        if (! $in || ! $out) {
            return false;
        }
        while (! gzeof($in)) {
            $chunk = gzread($in, 1 << 20);
            if ($chunk === false) {
                break;
            }
            fwrite($out, $chunk);
        }
        $ok = gzeof($in);
        gzclose($in);
        fclose($out);

        return $ok;
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
