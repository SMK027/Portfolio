<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Informations publiques d'un dépôt GitHub (étoiles, langage, dernier push…)
 * via l'API REST, mises en cache : la page ne dépend jamais de GitHub.
 */
class GithubRepositories
{
    protected const TTL = 12 * 3600;        // succès

    protected const FAILURE_TTL = 30 * 60;  // échec : nouvel essai plus tard

    /** « owner/repo » extrait d'une URL github.com, ou null. */
    public static function slug(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! in_array($host, ['github.com', 'www.github.com'], true)) {
            return null;
        }

        if (! preg_match('#^/([A-Za-z0-9-]{1,39})/([A-Za-z0-9._-]{1,100}?)(?:\.git)?(?:/.*)?$#', $parts['path'] ?? '', $m)) {
            return null;
        }

        return in_array(strtolower($m[1]), ['orgs', 'settings', 'topics', 'sponsors', 'marketplace'], true) ? null : $m[1].'/'.$m[2];
    }

    /** Données en cache ; récupérées si absentes (délai court, échec silencieux). */
    public function find(string $url): ?array
    {
        $slug = self::slug($url);
        if (! $slug) {
            return null;
        }

        $cached = Cache::get($this->key($slug));

        return $cached === null ? $this->refresh($slug) : ($cached ?: null);
    }

    public function refresh(string $slug): ?array
    {
        try {
            $response = Http::timeout(3)->connectTimeout(2)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->withUserAgent(config('app.name').' (portfolio)')
                ->when(config('services.github.token'), fn ($http, $token) => $http->withToken($token))
                ->get('https://api.github.com/repos/'.$slug);

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }

            $repo = $response->json();
            $data = [
                'name'        => $repo['full_name'] ?? $slug,
                'url'         => $repo['html_url'] ?? 'https://github.com/'.$slug,
                'description' => $repo['description'] ?? null,
                'stars'       => (int) ($repo['stargazers_count'] ?? 0),
                'forks'       => (int) ($repo['forks_count'] ?? 0),
                'language'    => $repo['language'] ?? null,
                'license'     => $repo['license']['spdx_id'] ?? null,
                'archived'    => (bool) ($repo['archived'] ?? false),
                'pushed_at'   => $repo['pushed_at'] ?? null,
                'topics'      => array_slice((array) ($repo['topics'] ?? []), 0, 6),
            ];
            Cache::put($this->key($slug), $data, self::TTL);

            return $data;
        } catch (\Throwable $e) {
            Log::info("GitHub : dépôt {$slug} indisponible — ".$e->getMessage());
            Cache::put($this->key($slug), false, self::FAILURE_TTL);

            return null;
        }
    }

    /** Rafraîchit tous les dépôts liés aux projets (tâche planifiée). */
    public function refreshAll(): int
    {
        return Project::with('links')->get()->flatMap->links
            ->map(fn ($link) => self::slug($link->url))->filter()->unique()
            ->each(fn ($slug) => $this->refresh($slug))->count();
    }

    public static function forget(string $url): void
    {
        if ($slug = self::slug($url)) {
            Cache::forget('github.repo.'.strtolower($slug));
        }
    }

    public static function pushedAt(array $repo): ?Carbon
    {
        return $repo['pushed_at'] ? Carbon::parse($repo['pushed_at']) : null;
    }

    protected function key(string $slug): string
    {
        return 'github.repo.'.strtolower($slug);
    }
}
