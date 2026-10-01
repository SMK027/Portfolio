<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte les pages vues du site public, sans cookie ni donnée personnelle :
 * - pas d'IP stockée, seulement une empreinte valable un jour (IP + navigateur
 *   + sel quotidien) pour compter les visiteurs uniques ;
 * - les personnes connectées, les robots, les préchargements et les
 *   navigateurs demandant à ne pas être suivis (DNT / GPC) sont ignorés.
 */
class RecordPageView
{
    /** Routes publiques comptabilisées. */
    public const ROUTES = [
        'home', 'formations', 'diplomes', 'certifications', 'competences', 'experiences', 'loisirs',
        'projects.index', 'projects.theme', 'projects.show', 'articles.index', 'articles.show',
        'contact.show', 'appointments.show', 'search',
    ];

    protected const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|uptime|curl|wget|python|httpclient|headless|lighthouse|pingdom/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            try {
                PageView::create([
                    'path'          => '/'.ltrim($request->path(), '/'),
                    'route'         => $request->route()?->getName(),
                    'referrer_host' => $this->referrerHost($request),
                    'visitor'       => substr(hash('sha256', now()->toDateString().config('app.key').$request->ip().$request->userAgent()), 0, 16),
                    'viewed_on'     => now()->toDateString(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Statistiques : page vue non enregistrée — '.$e->getMessage());
            }
        }

        return $response;
    }

    protected function shouldRecord(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && in_array($request->route()?->getName(), self::ROUTES, true)
            && ! $request->user()
            && ! $request->header('X-Livewire') && ! $request->ajax() && ! $request->wantsJson()
            && ! in_array(strtolower((string) $request->header('Sec-Purpose', $request->header('Purpose', ''))), ['prefetch', 'prerender'], true)
            && $request->header('DNT') !== '1' && $request->header('Sec-GPC') !== '1'
            && ! preg_match(self::BOTS, (string) $request->userAgent())
            && filled($request->userAgent());
    }

    /** Site d'origine du visiteur (hors liens internes). */
    protected function referrerHost(Request $request): ?string
    {
        $host = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        return $host !== '' && $host !== preg_replace('/^www\./', '', $request->getHost()) ? substr($host, 0, 100) : null;
    }
}
