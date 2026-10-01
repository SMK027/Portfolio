<?php

namespace App\Http\Controllers;

use App\Models\SecurityKey;
use App\Services\AuditTrail;
use App\Services\WebAuthn;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * « Mon compte » : activation de la double authentification (comptes humains
 * uniquement) par application (TOTP) et/ou clés de sécurité (WebAuthn).
 * Toute désactivation demande le mot de passe actuel.
 */
class TwoFactorController extends Controller
{
    protected const PENDING_SECRET = 'two_factor.pending_secret';

    public function __construct(protected AuditTrail $audit)
    {
    }

    /* ---------- Application d'authentification (TOTP) ---------- */

    /** Étape 1 : nouveau secret, gardé en session jusqu'à confirmation. */
    public function startTotp(Request $request): RedirectResponse
    {
        abort_if($request->user()->hasTotp(), 409);
        $request->session()->put(self::PENDING_SECRET, Crypt::encryptString(Totp::generateSecret()));

        return redirect()->to(route('profile.edit').'#double-authentification');
    }

    /** Étape 2 : le premier code valide active l'application. */
    public function confirmTotp(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']], [], ['code' => 'code']);
        $pending = $request->session()->get(self::PENDING_SECRET);
        $user = $request->user();

        $step = $pending ? Totp::verify(Crypt::decryptString($pending), $request->input('code')) : null;
        if ($step === null) {
            return back()->withErrors(['code' => 'Code incorrect : vérifiez l\'heure de votre téléphone et réessayez.'], 'twoFactor')
                ->withFragment('double-authentification');
        }

        $user->forceFill([
            'two_factor_secret'       => Crypt::decryptString($pending),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step'    => $step,
        ])->saveQuietly();
        $request->session()->forget(self::PENDING_SECRET);
        $this->audit->record('two_factor.enabled', $user, force: true);

        return $this->withRecoveryCodesIfMissing($request, 'Application d\'authentification activée.');
    }

    public function cancelTotp(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_SECRET);

        return redirect()->to(route('profile.edit').'#double-authentification');
    }

    public function disableTotp(Request $request): RedirectResponse
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);
        $user = $request->user();

        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->saveQuietly();
        $this->clearRecoveryCodesIfUnused($request);
        $this->audit->record('two_factor.disabled', $user, force: true);

        return redirect()->to(route('profile.edit').'#double-authentification')->with('success', 'Application d\'authentification désactivée.');
    }

    /* ---------- Clés de sécurité (WebAuthn) ---------- */

    public function keyOptions(Request $request, WebAuthn $webauthn): JsonResponse
    {
        return response()->json($webauthn->registrationOptions($request->user()));
    }

    public function storeKey(Request $request, WebAuthn $webauthn): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'credential' => ['required', 'array'],
        ], [], ['name' => 'nom de la clé']);

        $webauthn->register($request->user(), $data['credential'], $data['name']);
        $this->withRecoveryCodesIfMissing($request, 'Clé de sécurité « '.$data['name'].' » enregistrée.');

        return response()->json(['redirect' => route('profile.edit').'#double-authentification']);
    }

    public function destroyKey(Request $request, SecurityKey $key): RedirectResponse
    {
        abort_unless($key->user_id === $request->user()->id, 404);
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);

        $key->delete();
        $this->clearRecoveryCodesIfUnused($request);

        return redirect()->to(route('profile.edit').'#double-authentification')->with('success', 'Clé « '.$key->name.' » supprimée.');
    }

    /* ---------- Codes de secours ---------- */

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);
        abort_unless($request->user()->hasTwoFactor(), 409);

        $codes = $request->user()->generateRecoveryCodes();
        $this->audit->record('two_factor.recovery_codes_regenerated', $request->user(), force: true);

        return redirect()->to(route('profile.edit').'#double-authentification')
            ->with('recovery_codes', $codes)->with('success', 'Nouveaux codes de secours générés ; les anciens ne fonctionnent plus.');
    }

    /** Premier facteur activé : codes de secours générés et affichés une seule fois. */
    protected function withRecoveryCodesIfMissing(Request $request, string $message): RedirectResponse
    {
        $user = $request->user();
        $response = redirect()->to(route('profile.edit').'#double-authentification')->with('success', $message);

        if ($user->recoveryCodesLeft() === 0) {
            $response->with('recovery_codes', $user->generateRecoveryCodes());
        }

        return $response;
    }

    /** Plus aucun facteur : les codes de secours n'ont plus de raison d'être. */
    protected function clearRecoveryCodesIfUnused(Request $request): void
    {
        $user = $request->user()->fresh();
        if (! $user->hasTwoFactor()) {
            $user->forceFill(['two_factor_recovery_codes' => null])->saveQuietly();
        }
    }
}
