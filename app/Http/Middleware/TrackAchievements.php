<?php

namespace App\Http\Middleware;

use App\Services\Achievements;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Succès : fait avancer les déclencheurs de navigation (pages vues, jours de visite…)
 * et annonce les succès débloqués pendant la requête :
 * - page HTML : données injectées avant </body>, lues par resources/js/achievements.js ;
 * - réponse JSON : en-tête X-Achievements (JSON en base64) ;
 * - redirection, téléchargement : conservés en session pour la page suivante.
 */
class TrackAchievements
{
    public const SESSION_KEY = 'achievements.toasts';

    public const HEADER = 'X-Achievements';

    /** Routes publiques et section correspondante (pour « sections différentes visitées »). */
    protected const SECTIONS = [
        'home' => 'home', 'formations' => 'formations', 'diplomes' => 'diplomes', 'certifications' => 'certifications',
        'competences' => 'competences', 'experiences' => 'experiences', 'loisirs' => 'loisirs',
        'projects.index' => 'projets', 'projects.theme' => 'projets', 'projects.show' => 'projets',
        'articles.index' => 'veille', 'articles.show' => 'veille', 'contact.show' => 'contact',
        'appointments.show' => 'rendez-vous', 'recommendations.index' => 'recommandations',
        'terminal' => 'terminal', 'sandbox.show' => 'machine-ssh',
    ];

    public function __construct(protected Achievements $achievements)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->achievements->enabled()) {
            return $response;
        }

        if ($this->isPageView($request, $response)) {
            $this->recordPageView($request);
        }

        $pending = $this->achievements->pullPending();

        if ($this->isHtml($response) && $response->getStatusCode() === 200) {
            $toasts = [...(array) $request->session()->pull(self::SESSION_KEY, []), ...$pending];
            if ($toasts) {
                $this->inject($response, $toasts);
            }
        } elseif ($pending && $response instanceof JsonResponse) {
            $response->headers->set(self::HEADER, base64_encode(json_encode($pending, JSON_UNESCAPED_UNICODE)));
        } elseif ($pending && $request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, [...(array) $request->session()->get(self::SESSION_KEY, []), ...$pending]);
        }

        return $response;
    }

    protected function isPageView(Request $request, Response $response): bool
    {
        return $request->isMethod('GET') && $response->getStatusCode() === 200 && $this->isHtml($response)
            && ! $request->ajax() && ! $request->wantsJson()
            && ! in_array(strtolower((string) $request->header('Sec-Purpose', $request->header('Purpose', ''))), ['prefetch', 'prerender'], true)
            && (isset(self::SECTIONS[$request->route()?->getName()]) || $request->routeIs('search', 'achievements.index', 'easter-eggs.show'));
    }

    protected function recordPageView(Request $request): void
    {
        $route = $request->route();
        $name = $route->getName();

        if (isset(self::SECTIONS[$name])) {
            $this->achievements->record('sections_visited', self::SECTIONS[$name]);
        }

        match ($name) {
            'projects.show'     => $this->achievements->record('projects_viewed', (string) $this->key($route->parameter('project'))),
            'articles.show'     => $this->achievements->record('articles_viewed', (string) $this->key($route->parameter('article'))),
            'terminal'          => $this->achievements->record('terminal_opened'),
            'search'            => filled($request->query('q')) ? $this->achievements->record('search_used') : null,
            'achievements.index' => $this->achievements->record('achievements_page'),
            'easter-eggs.show'  => $this->achievements->record('easter_egg_found'),
            default             => null,
        };

        $this->achievements->record('visit_days', now()->toDateString());
        if (now()->hour < 5) {
            $this->achievements->record('night_visit');
        }
    }

    protected function key(mixed $model): mixed
    {
        return is_object($model) && method_exists($model, 'getKey') ? $model->getKey() : $model;
    }

    protected function isHtml(Response $response): bool
    {
        return $response instanceof IlluminateResponse && ! $response instanceof RedirectResponse
            && str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html');
    }

    protected function inject(Response $response, array $toasts): void
    {
        $content = (string) $response->getContent();
        $position = strripos($content, '</body>');
        if ($position === false) {
            return;
        }

        $json = json_encode($toasts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $script = '<script type="application/json" id="achievement-toasts">'.$json.'</script>';
        $response->setContent(substr_replace($content, $script, $position, 0));
    }
}
