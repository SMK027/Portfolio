<?php

namespace App\Http\Controllers;

use App\Achievements\Triggers;
use App\Models\AchievementSettings;
use App\Services\Achievements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AchievementController extends Controller
{
    public function __construct(protected Achievements $achievements)
    {
    }

    /** Page publique : succès débloqués, verrouillés (et secrets masqués), progression. */
    public function index(): View
    {
        abort_unless($this->achievements->enabled(), 404);

        $board = $this->achievements->board();

        return view('public.achievements', [
            'settings' => AchievementSettings::current(),
            'groups'   => $board->groupBy(fn ($item) => $item['achievement']->category),
            'unlocked' => $board->whereNotNull('unlocked_at')->count(),
            'total'    => $board->count(),
        ]);
    }

    /** Événements signalés par le navigateur (palette, thème, temps passé). */
    public function event(Request $request): JsonResponse
    {
        abort_unless($this->achievements->enabled(), 404);
        $data = $request->validate(['event' => ['required', Rule::in(Triggers::CLIENT_EVENTS)]]);

        if ($data['event'] === 'minutes_spent') {
            // Une minute au plus par minute écoulée : le compteur ne peut pas être gonflé.
            $visitor = $this->achievements->visitor(create: $this->achievements->eligible());
            if (! $visitor || ($visitor->last_beat_at && $visitor->last_beat_at->gt(now()->subSeconds(55)))) {
                return response()->json(['ok' => true]);
            }
            $visitor->forceFill(['last_beat_at' => now()])->save();
        }

        $this->achievements->record($data['event']);

        return response()->json(['ok' => true]);
    }
}
