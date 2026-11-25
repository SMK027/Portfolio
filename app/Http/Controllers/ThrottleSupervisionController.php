<?php

namespace App\Http\Controllers;

use App\Models\Supervisor;
use App\Services\AuditTrail;
use App\Services\ThrottleSupervision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Erreur 429 : validation superviseur (identifiant + PIN), puis rejeu de la requête bloquée. */
class ThrottleSupervisionController extends Controller
{
    public function __construct(protected ThrottleSupervision $supervision, protected AuditTrail $audit)
    {
    }

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->supervision->pending($request)) {
            return redirect()->to(url('/'));
        }

        return view('errors.429', ['supervision' => true]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        try {
            return $this->validateAndReplay($request);
        } catch (ValidationException $e) {
            // Retour à l'écran superviseur (et non à la page d'origine, qui a été bloquée).
            return redirect()->route('throttle-supervision.show')->withErrors($e->errors())->withInput($request->only('username'));
        }
    }

    protected function validateAndReplay(Request $request): View|RedirectResponse
    {
        $pending = $this->supervision->pending($request);
        if (! $pending) {
            return redirect()->to(url('/'))->with('error', 'Aucune opération en attente (délai dépassé ?). Recommencez.');
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'pin'      => ['required', 'digits_between:4,8'],
        ], [], ['username' => 'identifiant superviseur', 'pin' => 'code PIN']);
        $operation = $pending['method'].' /'.$pending['path'];

        $key = 'throttle-supervision:'.$request->ip().'|'.strtolower($data['username']);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->audit->record('supervision.locked', Supervisor::where('username', $data['username'])->first(), meta: [
                'identifiant' => $data['username'], 'requête' => $operation,
            ], force: true);

            throw ValidationException::withMessages(['pin' => 'Trop de tentatives : réessayez dans '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).']);
        }

        $supervisor = Supervisor::with('user')->where('username', $data['username'])->first();
        $valid = $supervisor && $supervisor->checkPin($data['pin']) && $supervisor->isUsable();

        if (! $valid || ! $supervisor->grantable([ThrottleSupervision::OPERATION])) {
            RateLimiter::hit($key, 300);
            $this->audit->record('supervision.failed', $supervisor, meta: [
                'identifiant' => $data['username'],
                'raison'      => ! $valid ? 'identifiant, PIN ou superviseur invalide' : 'opération non autorisée pour ce superviseur',
                'opération'   => $operation,
            ], force: true);

            throw ValidationException::withMessages(['pin' => ! $valid
                ? 'Identifiant ou code PIN incorrect, ou superviseur désactivé.'
                : 'Ce superviseur n\'est pas habilité à valider cette opération.']);
        }

        RateLimiter::clear($key);
        $supervisor->forceFill(['last_used_at' => now()])->saveQuietly();
        $this->audit->record('supervision.granted', $supervisor, meta: [
            'compte'     => $request->user()?->name ?? 'visiteur',
            'opérations' => [ThrottleSupervision::OPERATION],
            'requête'    => $operation,
        ], force: true);

        $token = $this->supervision->grant($request, $pending, $supervisor);

        if ($pending['method'] === 'GET') {
            return redirect()->to($pending['url'].(str_contains($pending['url'], '?') ? '&' : '?').ThrottleSupervision::BYPASS_FIELD.'='.$token);
        }

        return view('errors.429-replay', [
            'action' => $pending['url'],
            'method' => $pending['method'],
            'fields' => \App\Services\Supervision::flatten($pending['input']) + [ThrottleSupervision::BYPASS_FIELD => $token],
        ]);
    }
}
