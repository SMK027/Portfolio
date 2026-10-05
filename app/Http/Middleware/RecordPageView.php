<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Services\GeoIp;
use App\Support\UserAgent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mesure d'audience du site public (usage statistique uniquement) :
 * - IP (effacée après 3 mois), pays (base locale), appareil, navigateur ;
 * - cookie visiteur propre au site (13 mois) pour distinguer 1re visite et
 *   visite récurrente, cookie de visite (30 min) pour le temps par visite ;
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

    /** Cookie visiteur (13 mois max., exemption CNIL) et cookie de visite (30 min d'inactivité). */
    public const VISITOR_COOKIE = 'pv_vid';

    public const SESSION_COOKIE = 'pv_sid';

    public function __construct(protected GeoIp $geoip)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Identifiant préparé avant le rendu : la page l'utilise pour envoyer sa durée de lecture.
        $eligible = $this->isEligible($request);
        if ($eligible) {
            $request->attributes->set('page_view_uuid', (string) Str::uuid());
        }

        $response = $next($request);

        if ($eligible && $response->getStatusCode() === 200) {
            try {
                $visitorId = $this->validUuid($request->cookie(self::VISITOR_COOKIE));
                $sessionId = $this->validUuid($request->cookie(self::SESSION_COOKIE)) ?? (string) Str::uuid();
                $agent = UserAgent::parse($request->userAgent());

                PageView::create([
                    'uuid'           => $request->attributes->get('page_view_uuid'),
                    'path'           => '/'.ltrim($request->path(), '/'),
                    'route'          => $request->route()?->getName(),
                    'referrer_host'  => $this->referrerHost($request),
                    'visitor'        => substr(hash('sha256', now()->toDateString().config('app.key').$request->ip().$request->userAgent()), 0, 16),
                    'viewed_on'      => now()->toDateString(),
                    'ip_address'     => $request->ip(),
                    'country'        => $this->geoip->country($request->ip(), $request->header('CF-IPCountry')),
                    'device'         => $agent['device'],
                    'browser'        => $agent['browser'],
                    'os'             => $agent['os'],
                    'visitor_id'     => $visitorId ?? ($visitorId = (string) Str::uuid()),
                    'session_id'     => $sessionId,
                    // Nouveau visiteur : pas encore de cookie, ou visite en cours commencée sans cookie.
                    'is_new_visitor' => ! $request->cookie(self::VISITOR_COOKIE)
                        || PageView::where('session_id', $sessionId)->where('is_new_visitor', true)->exists(),
                ]);

                $response->headers->setCookie(cookie(self::VISITOR_COOKIE, $visitorId, 60 * 24 * 395, httpOnly: true, sameSite: 'lax'));
                $response->headers->setCookie(cookie(self::SESSION_COOKIE, $sessionId, 30, httpOnly: true, sameSite: 'lax'));
            } catch (\Throwable $e) {
                Log::warning('Statistiques : page vue non enregistrée — '.$e->getMessage());
            }
        }

        return $response;
    }

    protected function validUuid(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    protected function isEligible(Request $request): bool
    {
        return $request->isMethod('GET')
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
