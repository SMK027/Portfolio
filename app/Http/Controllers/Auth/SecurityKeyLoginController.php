<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\LoginBan;
use App\Services\WebAuthn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Connexion directe au panel avec une clé de sécurité (sans mot de passe),
 * réservée aux administrateurs et au personnel. La clé et sa vérification (PIN, biométrie)
 * valent double authentification : aucune étape supplémentaire.
 */
class SecurityKeyLoginController extends Controller
{
    protected const MAX_ATTEMPTS = 10;

    public function options(Request $request, WebAuthn $webauthn): JsonResponse
    {
        $request->validate(['email' => ['nullable', 'string', 'max:255']]);
        // Clés non résidentes : l'e-mail (facultatif) permet de proposer celles du compte.
        // Réponse identique que le compte existe ou non (pas de divulgation).
        $user = filled($request->input('email')) ? User::where('email', $request->input('email'))->first() : null;

        return response()->json($webauthn->loginOptions($user));
    }

    public function store(Request $request, WebAuthn $webauthn, AuditTrail $audit, LoginBan $bans): JsonResponse
    {
        $key = 'key-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json(['message' => 'Trop de tentatives : réessayez dans '.RateLimiter::availableIn($key).' secondes.'], 429);
        }

        $user = $webauthn->verifyLogin((array) $request->input('credential'));
        if (! $user) {
            RateLimiter::hit($key, 300);
            $audit->record('auth.failed', null, meta: ['méthode' => 'clé de sécurité (sans mot de passe)'], force: true);
            $bans->recordFailure($request->ip(), 'clé de sécurité');

            return response()->json(['message' => 'Clé refusée : elle n\'est pas enregistrée sur un compte administrateur ou personnel actif, ou la vérification (PIN, empreinte) a échoué.'], 422);
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $audit->record('auth.security_key_login', $user, force: true);

        return response()->json(['redirect' => redirect()->intended(route('dashboard', absolute: false))->getTargetUrl()]);
    }
}
