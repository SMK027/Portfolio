<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Récupère une image désignée par une URL (collage dans l'éditeur) et la
 * stocke sur le disque public.
 *
 * Protections contre les requêtes vers le réseau interne (SSRF) :
 * - schémas http / https uniquement, ports 80 / 443 ;
 * - toutes les adresses IP résolues doivent être publiques ;
 * - la connexion est forcée sur l'IP vérifiée (pas de « DNS rebinding ») ;
 * - chaque redirection est revérifiée (3 au maximum) ;
 * - taille limitée pendant le transfert et type réel vérifié (JPEG, PNG, GIF, WebP).
 * Les images « data: » (captures collées) sont décodées sans requête réseau.
 */
class RemoteImageFetcher
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    protected const MAX_REDIRECTS = 3;

    protected const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /** Stocke l'image et retourne son URL relative (/storage/…). */
    public function fetch(string $url, string $directory): string
    {
        $binary = str_starts_with($url, 'data:')
            ? $this->decodeDataUri($url)
            : $this->download($url);

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: '';
        $extension = self::MIME_EXTENSIONS[$mime] ?? throw new RuntimeException("Type de fichier non accepté ({$mime}).");

        // Redimensionnée et convertie en WebP lorsque c'est possible et utile.
        if ($optimized = app(ImageOptimizer::class)->optimize($binary)) {
            [$binary, $extension] = [$optimized['binary'], $optimized['extension']];
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return '/storage/'.$path;
    }

    protected function decodeDataUri(string $uri): string
    {
        if (! preg_match('#^data:image/[a-z+.-]+;base64,(.+)$#is', $uri, $m)) {
            throw new RuntimeException('Image intégrée non reconnue.');
        }

        if (strlen($m[1]) > self::MAX_BYTES * 4 / 3 + 4) {
            throw new RuntimeException('Image trop volumineuse.');
        }

        $binary = base64_decode($m[1], true);

        return $binary !== false ? $binary : throw new RuntimeException('Image intégrée invalide.');
    }

    protected function download(string $url): string
    {
        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
            [$host, $port, $ip] = $this->validateUrl($url);

            $client = new Client([
                // Gestionnaire cURL imposé : nécessaire pour CURLOPT_RESOLVE (IP vérifiée).
                'handler'                       => HandlerStack::create(new CurlHandler),
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::CONNECT_TIMEOUT => 5,
                RequestOptions::TIMEOUT         => 15,
                RequestOptions::HTTP_ERRORS     => false,
                RequestOptions::HEADERS         => ['User-Agent' => 'Mozilla/5.0 (Portfolio image import)', 'Accept' => 'image/*'],
                // Interrompt le transfert dès que la taille maximale est dépassée.
                RequestOptions::PROGRESS        => function ($expected, $downloaded) {
                    if ($expected > self::MAX_BYTES || $downloaded > self::MAX_BYTES) {
                        throw new RuntimeException('Image trop volumineuse (8 Mo max).');
                    }
                },
                'curl'                          => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
            ]);

            try {
                $response = $client->get($url);
            } catch (Throwable $e) {
                throw new ImageDownloadException('Téléchargement impossible : '.($e->getPrevious()?->getMessage() ?? $e->getMessage()));
            }

            $status = $response->getStatusCode();

            if ($status >= 300 && $status < 400 && $response->hasHeader('Location')) {
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->getHeaderLine('Location')));

                continue;
            }

            if ($status !== 200) {
                // Accès refusé ou erreur temporaire : l'image existe sans doute (repli possible).
                // Introuvable (404, 410…) : inutile de garder un lien cassé.
                $exception = in_array($status, [401, 403, 429], true) || $status >= 500
                    ? ImageDownloadException::class
                    : RuntimeException::class;

                throw new $exception("Le serveur distant a répondu {$status}.");
            }

            $binary = (string) $response->getBody();
            if (strlen($binary) > self::MAX_BYTES) {
                throw new RuntimeException('Image trop volumineuse (8 Mo max).');
            }

            return $binary;
        }

        throw new RuntimeException('Trop de redirections.');
    }

    /**
     * @return array{0: string, 1: int, 2: string} hôte, port, IP publique vérifiée
     */
    protected function validateUrl(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrlException('Adresse non autorisée.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new UnsafeUrlException('Port non autorisé.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new RuntimeException('Nom de domaine introuvable.');
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new UnsafeUrlException('Adresse interne non autorisée.');
            }
        }

        // IPv4 de préférence ; une IPv6 doit être entre crochets pour CURLOPT_RESOLVE.
        usort($ips, fn ($a, $b) => str_contains($a, ':') <=> str_contains($b, ':'));
        $ip = str_contains($ips[0], ':') ? '['.$ips[0].']' : $ips[0];

        return [$host, $port, $ip];
    }

    public function isPublicIp(string $ip): bool
    {
        // IPv4 encapsulée dans une IPv6 (::ffff:127.0.0.1) : on vérifie l'IPv4.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            $ip = $m[1];
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // Plage partagée des opérateurs (CGNAT) 100.64.0.0/10
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if (($long & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
    }
}
