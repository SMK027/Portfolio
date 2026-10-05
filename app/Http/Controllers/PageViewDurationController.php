<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Durée de lecture envoyée par la page (navigator.sendBeacon) en la quittant. */
class PageViewDurationController extends Controller
{
    /** Au-delà, l'onglet a sans doute été oublié ouvert. */
    public const MAX_SECONDS = 1800;

    public function __invoke(Request $request): Response
    {
        $id = (string) $request->input('id');
        $seconds = min(self::MAX_SECONDS, max(0, (int) $request->input('seconds')));

        // Seule une page vue récente peut recevoir une durée, qui ne fait qu'augmenter.
        if (Str::isUuid($id) && $seconds > 0) {
            PageView::where('uuid', $id)->where('created_at', '>=', now()->subHours(2))
                ->where(fn ($q) => $q->whereNull('duration')->orWhere('duration', '<', $seconds))
                ->update(['duration' => $seconds]);
        }

        return response()->noContent();
    }
}
