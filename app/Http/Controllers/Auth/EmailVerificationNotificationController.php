<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SafeMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request, SafeMailer $mailer): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $sent = $mailer->attempt(function () use ($request) {
            $request->user()->sendEmailVerificationNotification();

            return true;
        }, 'lien de vérification de l\'adresse e-mail');

        return $sent
            ? back()->with('status', 'verification-link-sent')
            : back()->withErrors(['email' => 'L\'e-mail n\'a pas pu être envoyé pour le moment. Réessayez plus tard.']);
    }
}
