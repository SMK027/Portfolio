<?php

namespace App\Services;

use App\Models\Article;
use App\Models\PageView;
use App\Models\Project;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Agrégats des pages vues pour le panel. */
class SiteStatistics
{
    public const PERIODS = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 365 => '12 mois'];

    public function summary(int $days): array
    {
        $from = now()->subDays($days - 1)->toDateString();
        $previousFrom = now()->subDays(2 * $days - 1)->toDateString();
        $base = fn () => PageView::where('viewed_on', '>=', $from);

        // Visiteurs uniques : empreinte distincte par jour, additionnée sur la période.
        $daily = $base()->selectRaw('viewed_on, count(*) as views, count(distinct visitor) as visitors')
            ->groupBy('viewed_on')->get()->keyBy(fn ($row) => $row->viewed_on->toDateString());

        $series = collect(CarbonPeriod::create($from, now()->toDateString()))->map(fn ($date) => [
            'date'     => $date,
            'views'    => (int) ($daily[$date->toDateString()]->views ?? 0),
            'visitors' => (int) ($daily[$date->toDateString()]->visitors ?? 0),
        ]);

        $previousViews = PageView::whereBetween('viewed_on', [$previousFrom, now()->subDays($days)->toDateString()])->count();

        // Visites (cookie de visite) : nombre, pages et durée totale par visite.
        $visits = $base()->whereNotNull('session_id')
            ->selectRaw('session_id, count(*) as pages, sum(coalesce(duration, 0)) as seconds, max(case when is_new_visitor then 1 else 0 end) as is_new')
            ->groupBy('session_id')->get();
        $timed = $base()->whereNotNull('duration');

        return [
            'views'         => $series->sum('views'),
            'visitors'      => $series->sum('visitors'),
            'previousViews' => $previousViews,
            'series'        => $series,
            'topPages'      => $this->withTitles($base()->select('path', 'route', DB::raw('count(*) as views'), DB::raw('avg(duration) as avg_duration'))
                ->groupBy('path', 'route')->orderByDesc('views')->limit(10)->get()),
            'visits'        => $visits->count(),
            'newVisits'     => $visits->where('is_new', 1)->count(),
            'avgVisitTime'  => $visits->where('seconds', '>', 0)->avg('seconds'),
            'avgPageTime'   => (clone $timed)->avg('duration'),
            'pagesPerVisit' => $visits->avg('pages'),
            'countries'     => $base()->select('country', DB::raw('count(*) as views'), DB::raw('count(distinct visitor_id) as visitors'))
                ->groupBy('country')->orderByDesc('views')->limit(15)->get(),
            'cities'        => $base()->whereNotNull('city')->select('country', 'region', 'city', DB::raw('count(*) as views'), DB::raw('count(distinct visitor_id) as visitors'))
                ->groupBy('country', 'region', 'city')->orderByDesc('views')->limit(15)->get(),
            'devices'       => $base()->whereNotNull('device')->select('device', DB::raw('count(*) as views'))->groupBy('device')->orderByDesc('views')->get(),
            'browsers'      => $base()->whereNotNull('browser')->select('browser', DB::raw('count(*) as views'))->groupBy('browser')->orderByDesc('views')->limit(8)->get(),
            'systems'       => $base()->whereNotNull('os')->select('os', DB::raw('count(*) as views'))->groupBy('os')->orderByDesc('views')->limit(8)->get(),
            'referrers'     => $base()->whereNotNull('referrer_host')->select('referrer_host', DB::raw('count(*) as views'))
                ->groupBy('referrer_host')->orderByDesc('views')->limit(10)->get(),
        ];
    }

    /** Titre lisible de chaque page (article, projet ou page du menu). */
    protected function withTitles(Collection $rows): Collection
    {
        $slug = fn ($path) => basename($path);
        $articles = Article::whereIn('slug', $rows->where('route', 'articles.show')->map(fn ($r) => $slug($r->path)))->pluck('title', 'slug');
        $projects = Project::whereIn('slug', $rows->where('route', 'projects.show')->map(fn ($r) => $slug($r->path)))->pluck('title', 'slug');

        return $rows->map(function ($row) use ($articles, $projects, $slug) {
            $row->title = match ($row->route) {
                'articles.show' => $articles[$slug($row->path)] ?? $row->path,
                'projects.show' => $projects[$slug($row->path)] ?? $row->path,
                'home'          => 'Accueil',
                default         => $row->path,
            };

            return $row;
        });
    }

    /** Dernières pages vues (détail par visiteur). */
    public function latest(int $perPage = 50)
    {
        return PageView::latest('id')->paginate($perPage, ['*'], 'visites')->withQueryString();
    }

    /** Pages vues plus récentes que $afterId (temps réel), de la plus récente à la plus ancienne. */
    public function since(int $afterId, int $limit = 100)
    {
        return PageView::where('id', '>', $afterId)->latest('id')->limit($limit)->get();
    }

    /** Sans battement depuis ce délai (secondes), une page n'est plus considérée comme ouverte. */
    public const ONLINE_SECONDS = 75;

    /**
     * Pages actuellement ouvertes et visibles : battement récent sans signal de départ,
     * ou page tout juste chargée (navigateur sans JavaScript ou premier battement à venir).
     */
    protected function onlineQuery()
    {
        $limit = now()->subSeconds(self::ONLINE_SECONDS);

        return PageView::whereNull('left_at')->where(fn ($q) => $q
            ->where('last_seen_at', '>=', $limit)
            ->orWhere(fn ($q) => $q->whereNull('last_seen_at')->where('created_at', '>=', $limit)));
    }

    /**
     * Visiteurs en ligne, avec la page qu'ils consultent (la plus récente s'ils en ont plusieurs ouvertes).
     *
     * @return \Illuminate\Support\Collection<int, PageView>
     */
    public function online()
    {
        return $this->onlineQuery()->latest('id')->limit(500)->get()
            ->unique(fn (PageView $view) => $view->visitor_id ?? $view->visitor)
            ->values();
    }

    public function activeVisitors(): int
    {
        return $this->online()->count();
    }

    /** Supprime les données de plus de 13 mois et efface les IP de plus de 3 mois. */
    public function prune(): int
    {
        PageView::whereNotNull('ip_address')->where('viewed_on', '<', now()->subMonths(3)->toDateString())->update(['ip_address' => null]);

        return PageView::where('viewed_on', '<', now()->subMonths(13)->toDateString())->delete();
    }

    /** « 2 min 05 s » */
    public static function duration(?float $seconds): string
    {
        if (! $seconds) {
            return '—';
        }
        $seconds = (int) round($seconds);

        return $seconds < 60 ? $seconds.' s' : intdiv($seconds, 60).' min '.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT).' s';
    }
}
