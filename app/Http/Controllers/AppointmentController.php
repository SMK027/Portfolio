<?php

namespace App\Http\Controllers;

use App\Mail\AppointmentOwnerMail;
use App\Mail\AppointmentVisitorMail;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\Profile;
use App\Services\AppointmentSlots;
use App\Services\Recaptcha;
use App\Services\SafeMailer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Prise de rendez-vous par les visiteurs (à confirmer depuis l'administration). */
class AppointmentController extends Controller
{
    public function __construct(protected SafeMailer $mailer)
    {
    }

    public function show(Request $request, AppointmentSlots $slots, Recaptcha $recaptcha): View
    {
        return view('public.appointments', [
            'page'             => $request->attributes->get('page'),
            'days'             => $slots->available(),
            'settings'         => AppointmentSettings::current(),
            'recaptchaSiteKey' => $recaptcha->siteKey(),
        ]);
    }

    public function store(Request $request, AppointmentSlots $slots, Recaptcha $recaptcha): RedirectResponse
    {
        // Champ piège rempli : robot, demande ignorée sans le lui signaler.
        if ($request->filled('website')) {
            return redirect()->route('appointments.show')->with('success', 'Demande envoyée.');
        }

        $settings = AppointmentSettings::current();
        $data = $request->validate([
            'slot'    => ['required', 'date_format:Y-m-d H:i'],
            'name'    => ['required', 'string', 'max:150'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'topic'   => ['required', Rule::in($settings['topics'])],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ], [
            'slot.required'    => 'Choisissez un créneau.',
            'consent.accepted' => 'Vous devez accepter l\'utilisation de vos coordonnées pour organiser le rendez-vous.',
        ], ['name' => 'nom', 'phone' => 'téléphone', 'topic' => 'sujet']);

        if (! $recaptcha->verify($request->input('recaptcha_token'), 'appointment', $request->ip())) {
            throw ValidationException::withMessages(['recaptcha' => 'La vérification anti-robot a échoué. Rechargez la page et réessayez.']);
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['slot']);
        $end = $start->addMinutes((int) $settings['duration']);

        // Verrou : deux visiteurs ne peuvent pas réserver le même créneau.
        $appointment = DB::transaction(function () use ($data, $start, $end, $slots, $request) {
            Appointment::holding()->overlapping($start, $end)->lockForUpdate()->get();
            if (! $slots->isAvailable($start)) {
                throw ValidationException::withMessages(['slot' => 'Ce créneau vient d\'être réservé ou n\'est plus disponible : choisissez-en un autre.']);
            }

            return Appointment::create([
                ...collect($data)->only(['name', 'email', 'phone', 'topic', 'message'])->all(),
                'starts_at'    => $start,
                'ends_at'      => $end,
                'status'       => 'pending',
                'cancel_token' => Str::random(48),
                'ip_address'   => $request->ip(),
                'consented_at' => now(),
            ]);
        });

        $this->mailer->queue($appointment->email, new AppointmentVisitorMail($appointment, AppointmentVisitorMail::RECEIVED), 'accusé de rendez-vous');
        $this->mailer->queue($this->ownerEmail(), new AppointmentOwnerMail($appointment, AppointmentOwnerMail::REQUESTED), 'notification de rendez-vous');

        return redirect()->route('appointments.show')->with('success',
            'Demande envoyée pour le '.$start->translatedFormat('l j F à H:i').'. Vous recevrez un e-mail de confirmation.');
    }

    /** Annulation par le visiteur (lien reçu par e-mail). */
    public function cancelForm(string $token): View
    {
        return view('public.appointment-cancel', ['appointment' => Appointment::where('cancel_token', $token)->firstOrFail()]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $appointment = Appointment::where('cancel_token', $token)->firstOrFail();
        if ($appointment->canBeCancelled()) {
            $appointment->update(['status' => 'cancelled']);
            $this->mailer->queue($this->ownerEmail(), new AppointmentOwnerMail($appointment, AppointmentOwnerMail::CANCELLED), 'annulation de rendez-vous');
        }

        return redirect()->route('appointments.cancel', $token);
    }

    protected function ownerEmail(): ?string
    {
        return config('services.contact.recipient') ?: Profile::current()->email ?: config('mail.from.address');
    }
}
