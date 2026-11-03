<?php

namespace App\Http\Controllers;

use App\Models\SandboxAccount;
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
        ]);
    }

    /** Ticket à usage unique pour ouvrir le terminal web (vérifié par l'agent). */
    public function ticket(Request $request): JsonResponse
    {
        abort_unless($this->sandbox->isOpen(), 503, 'L\'accès à la machine est fermé.');

        return response()->json([
            'url' => $this->sandbox->terminalUrl().'?ticket='.$this->sandbox->terminalTicket($request->ip()),
        ])->header('Cache-Control', 'no-store');
    }
}
