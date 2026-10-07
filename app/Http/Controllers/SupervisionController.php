<?php

namespace App\Http\Controllers;

use App\Models\Supervisor;
use App\Services\AuditTrail;
use App\Services\Supervision;
use App\Support\ServicePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Écran « Validation superviseur » : identifiant + PIN, puis rejeu de l'opération. */
class SupervisionController extends Controller
{
    public function __construct(protected Supervision $supervision, protected AuditTrail $audit)
    {
    }

    public function show(Request $request): View|RedirectResponse
    {
        $pending = $this->supervision->pending($request);
        if (! $pending) {
            return redirect()->route('dashboard')->with('error', 'Aucune opération en attente de validation.');
        }

        return view('supervision.challenge', [
            'pending'    => $pending,
            'operations' => collect($pending['operations'])->map(fn ($op) => ServicePermissions::label($op)),
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        $pending = $this->supervision->pending($request);
        if (! $pending) {
            return redirect()->route('dashboard')->with('error', 'Aucune opération en attente (délai dépassé ?). Recommencez.');
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'pin'      => ['required', 'digits_between:4,8'],
        ], [], ['username' => 'identifiant superviseur', 'pin' => 'code PIN']);

        $key = 'supervision:'.$request->user()->id.'|'.strtolower($data['username']);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['pin' => 'Trop de tentatives : réessayez dans '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).']);
        }

        $supervisor = Supervisor::with('user')->where('username', $data['username'])->first();
        $valid = $supervisor && $supervisor->checkPin($data['pin']) && $supervisor->isUsable();

        if (! $valid || ! $supervisor->grantable($pending['operations'])) {
            RateLimiter::hit($key, 300);
            $this->audit->record('supervision.failed', $supervisor, meta: [
                'identifiant' => $data['username'],
                'raison'      => ! $valid ? 'identifiant, PIN ou superviseur invalide' : 'opération non autorisée pour ce superviseur',
                'opération'   => $pending['method'].' /'.$pending['path'],
            ], force: true);

            throw ValidationException::withMessages(['pin' => ! $valid
                ? 'Identifiant ou code PIN incorrect, ou superviseur désactivé.'
                : 'Ce superviseur n\'est pas habilité à valider cette opération.']);
        }

        RateLimiter::clear($key);
        $supervisor->forceFill(['last_used_at' => now()])->saveQuietly();
        $this->audit->record('supervision.granted', $supervisor, meta: [
            'compte'     => $request->user()->name,
            'opérations' => $supervisor->grantable($pending['operations']),
            'requête'    => $pending['method'].' /'.$pending['path'],
        ], force: true);

        $token = $this->supervision->grant($request, $pending, $supervisor);

        // Consultation : rouverte ; formulaire : renvoyé automatiquement avec les champs saisis.
        if ($pending['method'] === 'GET') {
            return redirect()->to($pending['url'].(str_contains($pending['url'], '?') ? '&' : '?').Supervision::BYPASS_FIELD.'='.$token);
        }

        return view('supervision.replay', [
            'action' => $pending['url'],
            'method' => $pending['method'],
            'fields' => Supervision::flatten($pending['input']) + [Supervision::BYPASS_FIELD => $token],
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $pending = $this->supervision->pending($request);
        $this->supervision->discardPending($request);

        return redirect()->to($pending['referer'] ?? route('dashboard'))->with('error', 'Opération annulée.');
    }
}
