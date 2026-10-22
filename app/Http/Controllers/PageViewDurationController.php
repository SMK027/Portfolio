<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Signal envoyé par la page (navigator.sendBeacon) : battement toutes les 30 s
 * tant qu'elle est visible, puis signal de départ (« left ») quand elle est
 * masquée ou quittée. Met à jour la durée de lecture et la présence en temps réel.
 */
class PageViewDurationController extends Controller
{
    /** Au-delà, l'onglet a sans doute été oublié ouvert. */
    public const MAX_SECONDS = 1800;

    public function __invoke(Request $request): Response
    {
        $id = (string) $request->input('id');
        $seconds = min(self::MAX_SECONDS, max(0, (int) $request->input('seconds')));

        // Seule une page vue récente est concernée ; la durée ne fait qu'augmenter.
        if (Str::isUuid($id)) {
            $view = PageView::where('uuid', $id)->where('created_at', '>=', now()->subHours(2))->first();

            if ($view) {
                $view->last_seen_at = now();
                $view->left_at = $request->boolean('left') ? now() : null;
                if ($seconds > 0 && $seconds > (int) $view->duration) {
                    $view->duration = $seconds;
                }
                $view->save();
            }
        }

        return response()->noContent();
    }
}
