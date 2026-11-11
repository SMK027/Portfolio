<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageReceived;
use App\Mail\ContactVerificationCode;
use App\Models\ContactMessage;
use App\Models\ContactVerification;
use App\Models\Profile;
use App\Models\Supervisor;
use App\Services\AuditTrail;
use App\Services\DiscordNotifier;
use App\Services\Recaptcha;
use App\Services\SafeMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Formulaire de contact en deux étapes : le message saisi est mis en attente et un code
 * à 6 chiffres est envoyé à l'adresse indiquée ; il n'est enregistré (et notifié) qu'une
 * fois ce code confirmé. Au-delà de ContactVerification::MAX_SENDS envois de code, l'adresse
 * IP et l'adresse e-mail sont bloquées pendant ContactVerification::BLOCK_HOURS heures.
 * Un superviseur habilité (SKIP_OPERATION) peut valider l'envoi sans le code.
 */
class ContactController extends Controller
{
    private const SESSION_KEY = 'contact_verification_id';

    /** Habilitation superviseur permettant de se passer du code de vérification. */
    public const SKIP_OPERATION = 'contact.skip_verification';

    public function show(Request $request, Recaptcha $recaptcha): View
    {
        $blockedUntil = ContactVerification::blockedUntil($request->ip(), null);

        return view('public.contact', [
            'page'             => $request->attributes->get('page'),
            'profile'          => Profile::current(),
            'recaptchaSiteKey' => $recaptcha->siteKey(),
            'blockedUntil'     => $blockedUntil,
        ]);
    }

    public function store(ContactRequest $request, Recaptcha $recaptcha, SafeMailer $mailer): RedirectResponse
    {
        if (! $recaptcha->verify($request->input('recaptcha_token'), 'contact', $request->ip())) {
            throw ValidationException::withMessages([
                'recaptcha' => 'La vérification anti-robot a échoué. Rechargez la page et réessayez.',
            ]);
        }

        $email = $request->validated('email');
        $this->ensureNotBlocked($request->ip(), $email, 'email');

        $verification = $this->sendCode($mailer, [
            'email'           => $email,
            'ip_address'      => $request->ip(),
            'payload'         => $request->safe()->only(['first_name', 'last_name', 'email', 'subject', 'message']),
            'recaptcha_score' => $recaptcha->lastScore(),
        ], 'email');

        $request->session()->put(self::SESSION_KEY, $verification->id);

        return redirect()->route('contact.verification');
    }

    public function verification(Request $request): View|RedirectResponse
    {
        $verification = $this->pending($request);
        if (! $verification) {
            return redirect()->route('contact.show');
        }

        return view('public.contact-verification', [
            'page'         => $request->attributes->get('page'),
            'email'        => $verification->email,
            'expired'      => ! $verification->isUsable(),
            'remaining'    => ContactVerification::remainingSends($request->ip(), $verification->email),
            'blockedUntil' => ContactVerification::blockedUntil($request->ip(), $verification->email),
            'minutes'      => ContactVerification::TTL_MINUTES,
            'supervisable' => $this->anySupervisor(),
        ]);
    }

    public function verify(Request $request, SafeMailer $mailer): RedirectResponse
    {
        $verification = $this->pending($request);
        if (! $verification) {
            return redirect()->route('contact.show')
                ->with('error', 'Votre demande a expiré : merci de remplir à nouveau le formulaire.');
        }

        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        if (strlen($code) !== 6) {
            throw ValidationException::withMessages(['code' => 'Saisissez le code à 6 chiffres reçu par e-mail.']);
        }

        if (! $verification->isUsable()) {
            throw ValidationException::withMessages(['code' => 'Ce code a expiré ou a été trop souvent mal saisi : demandez-en un nouveau.']);
        }

        if (! $verification->matches($code)) {
            $verification->increment('attempts');
            $left = ContactVerification::MAX_ATTEMPTS - $verification->attempts;
            throw ValidationException::withMessages(['code' => $left > 0
                ? "Code incorrect. Il vous reste {$left} essai(s)."
                : 'Code incorrect. Ce code n\'est plus valable : demandez-en un nouveau.']);
        }

        return $this->complete($request, $mailer, $verification,
            'Merci ! Votre adresse e-mail est confirmée et votre message a bien été envoyé, je vous répondrai rapidement.');
    }

    /** Envoi validé par un superviseur (identifiant + PIN) à la place du code reçu par e-mail. */
    public function supervise(Request $request, SafeMailer $mailer, AuditTrail $audit): RedirectResponse
    {
        $verification = $this->pending($request);
        if (! $verification) {
            return redirect()->route('contact.show')
                ->with('error', 'Votre demande a expiré : merci de remplir à nouveau le formulaire.');
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'pin'      => ['required', 'digits_between:4,8'],
        ], [], ['username' => 'identifiant superviseur', 'pin' => 'code PIN']);

        $key = 'contact-supervision:'.$request->ip().'|'.strtolower($data['username']);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $audit->record('supervision.locked', Supervisor::where('username', $data['username'])->first(), meta: [
                'identifiant' => $data['username'],
                'requête'     => 'POST /'.$request->path(),
            ], force: true);

            throw ValidationException::withMessages(['pin' => 'Trop de tentatives : réessayez dans '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).']);
        }

        $supervisor = Supervisor::with('user')->where('username', $data['username'])->first();
        $valid = $supervisor && $supervisor->checkPin($data['pin']) && $supervisor->isUsable();

        if (! $valid || ! $supervisor->grantable([self::SKIP_OPERATION])) {
            RateLimiter::hit($key, 300);
            $audit->record('supervision.failed', $supervisor, meta: [
                'identifiant' => $data['username'],
                'raison'      => ! $valid ? 'identifiant, PIN ou superviseur invalide' : 'opération non autorisée pour ce superviseur',
                'opération'   => 'POST /'.$request->path(),
            ], force: true);

            throw ValidationException::withMessages(['pin' => ! $valid
                ? 'Identifiant ou code PIN incorrect, ou superviseur désactivé.'
                : 'Ce superviseur n\'est pas habilité à valider cette opération.']);
        }

        RateLimiter::clear($key);
        $supervisor->forceFill(['last_used_at' => now()])->saveQuietly();

        $response = $this->complete($request, $mailer, $verification,
            'Merci ! Votre message a bien été envoyé (validation superviseur), je vous répondrai rapidement.');

        $audit->record('supervision.granted', $supervisor, meta: [
            'opérations' => [self::SKIP_OPERATION],
            'e-mail'     => $verification->email,
            'message'    => $verification->contact_message_id,
            'requête'    => 'POST /'.$request->path(),
        ], force: true);

        return $response;
    }

    /** Message enregistré et notifié, demande de vérification close. */
    private function complete(Request $request, SafeMailer $mailer, ContactVerification $verification, string $success): RedirectResponse
    {
        $message = ContactMessage::create([
            ...$verification->payload,
            'consented_at'    => now(),
            'ip_address'      => $verification->ip_address,
            'recaptcha_score' => $verification->recaptcha_score,
        ]);
        $verification->forceFill(['verified_at' => now(), 'contact_message_id' => $message->id])->save();
        $request->session()->forget(self::SESSION_KEY);

        $this->notify($mailer, $message);

        return redirect()->route('contact.show')->with('success', $success);
    }

    private function anySupervisor(): bool
    {
        return Supervisor::with('user')->where('is_active', true)->get()
            ->contains(fn (Supervisor $s) => $s->isUsable() && $s->grantable([self::SKIP_OPERATION]));
    }

    public function resend(Request $request, SafeMailer $mailer): RedirectResponse
    {
        $verification = $this->pending($request);
        if (! $verification) {
            return redirect()->route('contact.show')
                ->with('error', 'Votre demande a expiré : merci de remplir à nouveau le formulaire.');
        }

        $this->ensureNotBlocked($request->ip(), $verification->email, 'code');

        $new = $this->sendCode($mailer, [
            'email'           => $verification->email,
            'ip_address'      => $request->ip(),
            'payload'         => $verification->payload,
            'recaptcha_score' => $verification->recaptcha_score,
        ], 'code');

        // L'ancien code n'est plus valable.
        $verification->forceFill(['expires_at' => now()])->save();
        $request->session()->put(self::SESSION_KEY, $new->id);

        return redirect()->route('contact.verification')
            ->with('success', 'Un nouveau code vient de vous être envoyé.');
    }

    private function pending(Request $request): ?ContactVerification
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id ? ContactVerification::query()->whereNull('verified_at')->find($id) : null;
    }

    private function ensureNotBlocked(?string $ip, string $email, string $field): void
    {
        if ($until = ContactVerification::blockedUntil($ip, $email)) {
            throw ValidationException::withMessages([$field => sprintf(
                'Vous avez atteint la limite de %d envois de code de vérification. Vous pourrez soumettre un nouveau message à partir du %s.',
                ContactVerification::MAX_SENDS,
                $until->timezone(config('app.timezone'))->translatedFormat('j F Y à H:i'),
            )]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sendCode(SafeMailer $mailer, array $attributes, string $field): ContactVerification
    {
        $code = ContactVerification::generateCode();
        $verification = ContactVerification::create([
            ...$attributes,
            'code_hash'  => ContactVerification::hashCode($code),
            'expires_at' => now()->addMinutes(ContactVerification::TTL_MINUTES),
        ]);

        // Envoi immédiat (hors file d'attente) : le visiteur attend ce code pour continuer.
        $sent = $mailer->send($verification->email, new ContactVerificationCode(
            $code,
            $attributes['payload']['first_name'] ?? '',
            ContactVerification::TTL_MINUTES,
        ), 'code de vérification du formulaire de contact');

        if (! $sent) {
            $verification->delete(); // un code jamais reçu ne compte pas dans la limite
            throw ValidationException::withMessages([
                $field => 'Impossible d\'envoyer le code de vérification pour le moment. Réessayez plus tard.',
            ]);
        }

        return $verification;
    }

    private function notify(SafeMailer $mailer, ContactMessage $message): void
    {
        $recipient = config('services.contact.recipient')
            ?: Profile::current()->email
            ?: config('mail.from.address');

        // Le message est déjà enregistré : un échec d'envoi (panne SMTP, configuration
        // invalide…) ne doit ni le perdre ni gêner le visiteur. Il reste lisible dans l'admin.
        // La date d'envoi (notified_at) est enregistrée une fois l'e-mail réellement parti.
        $mailer->queue($recipient, new ContactMessageReceived($message), 'notification de contact', markSent: $message);

        app(DiscordNotifier::class)->notify('contact', $message->subject, Str::limit($message->message, 500), [
            'De'         => trim($message->first_name.' '.$message->last_name),
            'E-mail'     => $message->email,
            'Adresse IP' => $message->ip_address ?? 'inconnue',
        ], route('admin.messages.show', $message));
    }
}
