<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SiteStatistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    public function __invoke(Request $request, SiteStatistics $statistics): View
    {
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
}
