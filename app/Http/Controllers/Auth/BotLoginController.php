<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ServiceToken;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Connexion des bots au panel par code d'application (jamais par mot de passe).
 * Le code utilisé est mémorisé en session et revérifié à chaque requête
 * (EnsureBotTokenIsValid) : sa désactivation ou suppression coupe l'accès.
 */
class BotLoginController extends Controller
{
    public const SESSION_KEY = 'bot_token_id';

    public function create(): View
    {
        return view('auth.bot-login');
    }

    public function store(Request $request, AuditTrail $audit): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:100']], [], ['code' => 'code d\'application']);

        $key = 'bot-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $plain = trim((string) $request->input('code'));
        $token = ServiceToken::findValid($plain);

        if (! $token || ! $token->user?->isBot() || ! $token->isUsable()) {
            RateLimiter::hit($key);
            $audit->record('auth.failed', $token?->user, meta: [
                'méthode' => 'code d\'application (bot)',
                'préfixe' => substr($plain, 0, 12),
            ], force: true);

            throw ValidationException::withMessages(['code' => 'Code invalide, désactivé, ou compte bot désactivé.']);
        }

        RateLimiter::clear($key);

        Auth::login($token->user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $token->id);
        $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
