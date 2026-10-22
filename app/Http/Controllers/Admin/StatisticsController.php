<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SiteStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    /** Fenêtre des « visiteurs actifs » de l'onglet temps réel. */
    public const ACTIVE_MINUTES = 5;

    public function __invoke(Request $request, SiteStatistics $statistics): View|JsonResponse
    {
        if ($request->query('onglet') === 'temps-reel') {
            return $this->live($request, $statistics);
        }

        $days = array_key_exists((int) $request->query('periode'), SiteStatistics::PERIODS) ? (int) $request->query('periode') : 30;

        return view('admin.statistics.index', [
            'days'    => $days,
            'stats'   => $statistics->summary($days),
            'latest'  => $statistics->latest(),
            // Adresses IP : administrateurs uniquement (pas les bots).
            'showIps' => $request->user()->isAdmin(),
            'geoipReady' => is_file(\App\Services\GeoIp::path()),
        ]);
    }

    /** Onglet « Temps réel » : historique complet, avec ajout automatique des nouvelles pages vues. */
    protected function live(Request $request, SiteStatistics $statistics): View|JsonResponse
    {
        $showIps = $request->user()->isAdmin();

        if ($request->has('depuis') && $request->expectsJson()) {
            $views = $statistics->since(max(0, (int) $request->query('depuis')));

            return response()->json([
                'count'   => $views->count(),
                'last_id' => $views->first()?->id,
                'active'  => $statistics->activeVisitors(self::ACTIVE_MINUTES),
                'html'    => $views->map(fn ($view) => view('admin.statistics._row', ['view' => $view, 'showIps' => $showIps, 'dateFormat' => 'd/m/Y H:i:s'])->render())->join(''),
            ]);
        }

        return view('admin.statistics.live', [
            'views'         => $statistics->latest(100),
            'activeNow'     => $statistics->activeVisitors(self::ACTIVE_MINUTES),
            'activeMinutes' => self::ACTIVE_MINUTES,
            'showIps'       => $showIps,
        ]);
    }
}
