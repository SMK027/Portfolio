<?php

namespace App\Http\Controllers;

use App\Achievements\SandboxChallenges;
use App\Models\SandboxAccount;
use App\Services\Achievements;
use App\Services\Sandbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Page publique de la machine SSH : instructions, compte invité et terminal web. */
class SandboxController extends Controller
{
    public function __construct(protected Sandbox $sandbox)
    {
    }

    public function show(Request $request): View
    {
        $guest = SandboxAccount::guest();

        return view('public.sandbox', [
            'page'       => $request->attributes->get('page'),
            'open'       => $this->sandbox->isOpen(),
            'schedule'   => $this->sandbox->scheduleLabel(),
            'internet'   => $this->sandbox->effectiveInternet(),
            'ephemeral'  => (bool) $this->sandbox->settings()['ephemeral_sessions'],
            'guest'      => $guest?->isActive() ? $guest : null,
            'sshCommand' => $this->sandbox->sshCommand($guest?->username ?? 'identifiant'),
            'challenges' => app(Achievements::class)->enabled() ? SandboxChallenges::ALL : [],
        ]);
    }

    /** Ticket à usage unique pour ouvrir le terminal web (vérifié par l'agent). */
    public function ticket(Request $request): JsonResponse
    {
        abort_unless($this->sandbox->isOpen(), 503, 'L\'accès à la machine est fermé.');
        app(Achievements::class)->record('sandbox_terminal_opened');

        return response()->json([
            'url' => $this->sandbox->terminalUrl().'?ticket='.$this->sandbox->terminalTicket($request->ip()),
        ])->header('Cache-Control', 'no-store');
    }

    /** Réponse à un défi Linux de la machine, pour débloquer les succès correspondants. */
    public function challenge(Request $request, Achievements $achievements): JsonResponse
    {
        abort_unless($achievements->enabled(), 404);
        $data = $request->validate([
            'number' => ['required', 'integer', 'between:1,'.count(SandboxChallenges::ALL)],
            'answer' => ['required', 'string', 'max:100'],
        ]);

        if (! SandboxChallenges::check((int) $data['number'], $data['answer'])) {
            return response()->json(['message' => 'Ce n\'est pas la bonne réponse. Persévérez !'], 422);
        }

        $achievements->record('sandbox_challenges', (string) $data['number']);

        return response()->json(['message' => 'Défi '.$data['number'].' validé : '.SandboxChallenges::ALL[(int) $data['number']][0].' !']);
    }
}
