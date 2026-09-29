<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SafeMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, SafeMailer $mailer): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        // Une panne ou une mauvaise configuration du serveur mail ne doit pas
        // provoquer d'erreur 500 : on informe simplement l'utilisateur.
        $status = null;
        $sent = $mailer->attempt(function () use ($request, &$status) {
            $status = Password::sendResetLink($request->only('email'));

            return $status === Password::RESET_LINK_SENT;
        }, 'lien de réinitialisation du mot de passe');

        if (! $sent) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'L\'e-mail n\'a pas pu être envoyé pour le moment. Réessayez plus tard ou contactez un administrateur.']);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
