<?php

namespace App\Http\Controllers;

use App\Models\EasterEgg;
use App\Services\EasterEggs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page secrète d'easter eggs. Sans lien signé ni accès de session, tout répond 404 :
 * la page ne se distingue pas d'une adresse inexistante.
 */
class EasterEggController extends Controller
{
    public function __construct(protected EasterEggs $eggs)
    {
    }

    /** Déclencheur réussi dans le navigateur : vérifié ici, puis lien signé temporaire. */
    public function trigger(Request $request): JsonResponse
    {
        $data = $request->validate(['keys' => ['array', 'max:64'], 'keys.*' => ['string', 'max:30'], 'click' => ['boolean']]);
        abort_unless($this->eggs->matchesTrigger($data['keys'] ?? [], $request->boolean('click')), 404);

        return response()->json(['url' => $this->eggs->signedUrl()]);
    }

    public function show(Request $request): Response
    {
        $isManager = (bool) $request->user()?->canUsePanel('easter_eggs.read|easter_eggs.write|easter_eggs.delete');

        if ($this->eggs->isEnabled() && $request->hasValidSignature()) {
            $this->eggs->grantAccess($request);

            // Lien signé consommé : l'adresse affichée (et partageable) ne donne plus accès.
            return redirect()->route('easter-eggs.show');
        }
        abort_unless($this->eggs->hasAccess($request) || $isManager, 404);

        $eggs = $this->eggs->visibleFor($request);
        $justUnlocked = $request->session()->pull('easter_eggs.just_unlocked');

        return $this->private(response()->view('public.easter-eggs', [
            'settings'     => $this->eggs->settings(),
            'eggs'         => $eggs,
            'unlocked'     => $eggs->filter(fn (EasterEgg $egg) => $this->eggs->isUnlocked($request, $egg))->pluck('id')->all(),
            'justUnlocked' => $justUnlocked,
            'preview'      => $isManager && ! $this->eggs->hasAccess($request),
        ]));
    }

    /** Combinaison propre d'un élément tapée sur la page. */
    public function unlock(Request $request, EasterEgg $egg): JsonResponse
    {
        abort_unless($this->eggs->hasAccess($request) && $egg->isLocked() && $this->eggs->isVisible($request, $egg), 404);
        $data = $request->validate(['keys' => ['required', 'array', 'max:64'], 'keys.*' => ['string', 'max:30']]);

        if (array_map(fn ($k) => mb_strtolower($k), $data['keys']) !== EasterEggs::keys($egg->unlock_sequence)) {
            return response()->json(['message' => 'Raté.'], 422);
        }

        $this->eggs->unlock($request, $egg);
        $request->session()->put('easter_eggs.just_unlocked', $egg->id);

        return response()->json(['ok' => true]);
    }

    /** Image d'un élément : stockée hors du dossier public, servie seulement s'il est visible et déverrouillé. */
    public function image(Request $request, EasterEgg $egg): Response
    {
        $isManager = (bool) $request->user()?->canUsePanel('easter_eggs.read|easter_eggs.write|easter_eggs.delete');
        $allowed = $isManager || ($this->eggs->hasAccess($request) && $this->eggs->isVisible($request, $egg) && $this->eggs->isUnlocked($request, $egg));
        abort_unless($allowed && $egg->image_path && Storage::disk('local')->exists($egg->image_path), 404);

        return $this->private(Storage::disk('local')->response($egg->image_path, null, ['Cache-Control' => 'private, max-age=600']));
    }

    protected function private(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
