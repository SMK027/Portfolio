<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SiteStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController extends Controller
{
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

    /** Export CSV des pages vues de la période (séparateur « ; » pour Excel). */
    public function export(Request $request, SiteStatistics $statistics): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $days = array_key_exists((int) $request->query('periode'), SiteStatistics::PERIODS) ? (int) $request->query('periode') : 30;
        $withIps = $request->user()->isAdmin();

        return response()->streamDownload(function () use ($statistics, $days, $withIps) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents lisibles dans Excel
            foreach ($statistics->exportRows($days, $withIps) as $row) {
                fputcsv($out, $row, ';', escape: '');
            }
            fclose($out);
        }, 'statistiques-'.now()->format('Y-m-d').'-'.$days.'j.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Onglet « Temps réel » : historique complet, avec ajout automatique des nouvelles pages vues. */
    protected function live(Request $request, SiteStatistics $statistics): View|JsonResponse
    {
        $showIps = $request->user()->isAdmin();

        if ($request->has('depuis') && $request->expectsJson()) {
            $views = $statistics->since(max(0, (int) $request->query('depuis')));
            $online = $statistics->online();

            return response()->json([
                'count'   => $views->count(),
                'last_id' => $views->first()?->id,
                'active'  => $online->count(),
                'online'  => view('admin.statistics._online', ['online' => $online, 'showIps' => $showIps])->render(),
                'html'    => $views->map(fn ($view) => view('admin.statistics._row', ['view' => $view, 'showIps' => $showIps, 'dateFormat' => 'd/m/Y H:i:s'])->render())->join(''),
            ]);
        }

        return view('admin.statistics.live', [
            'views'         => $statistics->latest(100),
            'online'        => $statistics->online(),
            'showIps'       => $showIps,
        ]);
    }
}
