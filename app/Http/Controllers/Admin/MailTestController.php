<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Services\SafeMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Envoie un e-mail de test à l'administrateur connecté.
 */
class MailTestController extends Controller
{
    public function __invoke(Request $request, SafeMailer $mailer): RedirectResponse
    {
        $email = $request->user()->email;

        if ($mailer->send($email, new TestMail, 'e-mail de test')) {
            return back()->with('success', "E-mail de test envoyé à {$email}. Vérifiez votre boîte de réception (et les indésirables).");
        }

        return back()->with('error', 'L\'e-mail de test n\'a pas pu être envoyé : '.($mailer->lastError()['message'] ?? 'erreur inconnue').'. Vérifiez les paramètres MAIL_* du fichier .env.');
    }
}
