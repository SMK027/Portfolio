<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\LoginBan;
use App\Services\WebAuthn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second facteur à la connexion : clé de sécurité, code d'application ou code
 * de secours. Le mot de passe a déjà été vérifié ; aucune session n'est
 * ouverte avant la réussite de cette étape.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'two_factor.login';

    protected const MAX_ATTEMPTS = 5;

    public function __construct(protected AuditTrail $audit)
    {
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Session expirée : reconnectez-vous.']);
        }

        return view('auth.two-factor-challenge', [
            'hasTotp' => $user->hasTotp(),
            'hasKeys' => $user->securityKeys()->exists(),
        ]);
    }

    /** Code d'application ou code de secours. */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request) ?? throw ValidationException::withMessages(['code' => 'Session expirée : reconnectez-vous.']);
        $data = $request->validate(['code' => ['nullable', 'string', 'max:20'], 'recovery_code' => ['nullable', 'string', 'max:30']]);
        $this->ensureIsNotRateLimited($user);

        $recovery = filled($data['recovery_code'] ?? null);
        $valid = $recovery ? $user->useRecoveryCode($data['recovery_code']) : $user->verifyTotp((string) ($data['code'] ?? ''));

        if (! $valid) {
            $this->failed($user, $recovery ? 'code de secours' : 'code d\'application');

            throw ValidationException::withMessages([$recovery ? 'recovery_code' : 'code' => $recovery ? 'Code de secours invalide ou déjà utilisé.' : 'Code invalide ou expiré.']);
        }

        return $this->complete($request, $user, $recovery ? 'code de secours' : 'code d\'application');
    }

    public function keyOptions(Request $request, WebAuthn $webauthn): JsonResponse
    {
        $user = $this->pendingUser($request);
        abort_unless($user && $user->securityKeys()->exists(), 403);

        return response()->json($webauthn->authenticationOptions($user));
    }

    public function verifyKey(Request $request, WebAuthn $webauthn): JsonResponse
    {
        $user = $this->pendingUser($request);
        abort_unless($user, 403);
        $this->ensureIsNotRateLimited($user);

        if (! $webauthn->verify($user, (array) $request->input('credential'))) {
            $this->failed($user, 'clé de sécurité');

            return response()->json(['message' => 'Clé de sécurité refusée.'], 422);
        }

        return response()->json(['redirect' => $this->complete($request, $user, 'clé de sécurité')->getTargetUrl()]);
    }

    protected function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! $pending || $pending['expires'] < now()->getTimestamp()) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $user = User::find($pending['id']);

        return $user && $user->hasTwoFactor() ? $user : null;
    }

    protected function complete(Request $request, User $user, string $method): RedirectResponse
    {
        $remember = (bool) $request->session()->pull(self::SESSION_KEY)['remember'];
        RateLimiter::clear($this->throttleKey($user));

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->audit->record('auth.two_factor', $user, meta: ['méthode' => $method], force: true);

        if ($method === 'code de secours') {
            session()->flash('warning', 'Code de secours utilisé : il en reste '.$user->recoveryCodesLeft().'. Pensez à en régénérer depuis « Mon compte ».');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    protected function failed(User $user, string $method): void
    {
        RateLimiter::hit($this->throttleKey($user), 300);
        $this->audit->record('auth.two_factor_failed', $user, meta: ['méthode' => $method], force: true);
        app(LoginBan::class)->recordFailure(request()->ip(), 'second facteur ('.$method.')');
    }

    protected function ensureIsNotRateLimited(User $user): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($user), self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey($user))]),
            ]);
        }
    }

    protected function throttleKey(User $user): string
    {
        return 'two-factor:'.$user->id;
    }
}
