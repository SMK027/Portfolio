<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountAccess;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/** Choix du mot de passe depuis un lien envoyé par un administrateur (invitation, réinitialisation). */
class AccountSetupController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'request' => $request,
            'action'  => route('account.setup.store'),
            'title'   => 'Choisir votre mot de passe',
            'submit'  => 'Enregistrer le mot de passe',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker(AccountAccess::BROKER)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password'          => Hash::make($request->password),
                    'remember_token'    => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Mot de passe enregistré : vous pouvez vous connecter.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Ce lien est invalide ou a expiré. Demandez-en un nouveau à un administrateur.']);
    }
}
