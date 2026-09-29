<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Profile;
use App\Services\Recaptcha;
use App\Services\SafeMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(Request $request, Recaptcha $recaptcha): View
    {
        return view('public.contact', [
            'page'             => $request->attributes->get('page'),
            'profile'          => Profile::current(),
            'recaptchaSiteKey' => $recaptcha->siteKey(),
        ]);
    }

    public function store(ContactRequest $request, Recaptcha $recaptcha, SafeMailer $mailer): RedirectResponse
    {
        if (! $recaptcha->verify($request->input('recaptcha_token'), 'contact', $request->ip())) {
            throw ValidationException::withMessages([
                'recaptcha' => 'La vérification anti-robot a échoué. Rechargez la page et réessayez.',
            ]);
        }

        $message = ContactMessage::create([
            ...$request->safe()->only(['first_name', 'last_name', 'email', 'subject', 'message']),
            'consented_at'    => now(),
            'ip_address'      => $request->ip(),
            'recaptcha_score' => $recaptcha->lastScore(),
        ]);

        $recipient = config('services.contact.recipient')
            ?: Profile::current()->email
            ?: config('mail.from.address');

        // Le message est déjà enregistré : un échec d'envoi (panne SMTP, configuration
        // invalide…) ne doit ni le perdre ni gêner le visiteur. Il reste lisible dans l'admin.
        if ($mailer->send($recipient, new ContactMessageReceived($message), 'notification de contact')) {
            $message->update(['notified_at' => now()]);
        }

        return redirect()->route('contact.show')
            ->with('success', 'Merci ! Votre message a bien été envoyé, je vous répondrai rapidement.');
    }
}
