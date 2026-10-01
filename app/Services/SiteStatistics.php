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

        return [
            'views'         => $series->sum('views'),
            'visitors'      => $series->sum('visitors'),
            'previousViews' => $previousViews,
            'series'        => $series,
            'topPages'      => $this->withTitles($base()->select('path', 'route', DB::raw('count(*) as views'))
                ->groupBy('path', 'route')->orderByDesc('views')->limit(10)->get()),
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

    /** Supprime les données de plus de 13 mois. */
    public function prune(): int
    {
        return PageView::where('viewed_on', '<', now()->subMonths(13)->toDateString())->delete();
    }
}
