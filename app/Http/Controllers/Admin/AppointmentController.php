<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AppointmentVisitorMail;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\AvailabilityClosure;
use App\Models\AvailabilityRule;
use App\Services\SafeMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Rendez-vous : demandes, décisions et disponibilités. */
class AppointmentController extends Controller
{
    public const FILTERS = ['a-venir' => 'À venir', 'en-attente' => 'En attente', 'passes' => 'Passés', 'tous' => 'Tous'];

    public function index(Request $request): View
    {
        $filter = array_key_exists($request->query('filtre'), self::FILTERS) ? $request->query('filtre') : 'a-venir';
        $query = Appointment::query();
        match ($filter) {
            'a-venir'    => $query->where('starts_at', '>=', now())->whereIn('status', Appointment::HOLDING)->orderBy('starts_at'),
            'en-attente' => $query->where('status', 'pending')->where('starts_at', '>=', now())->orderBy('starts_at'),
            'passes'     => $query->where('starts_at', '<', now())->orderByDesc('starts_at'),
            default      => $query->orderByDesc('starts_at'),
        };

        return view('admin.appointments.index', [
            'appointments' => $query->paginate(30)->withQueryString(),
            'filter'       => $filter,
            'pending'      => Appointment::where('status', 'pending')->where('starts_at', '>=', now())->count(),
        ]);
    }

    /** Confirmer ou refuser, avec un message facultatif pour le visiteur. */
    public function decide(Request $request, Appointment $appointment, SafeMailer $mailer): RedirectResponse
    {
        $data = $request->validate([
            'decision'   => ['required', 'in:confirmed,declined'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ], [], ['admin_note' => 'message']);
        abort_unless($appointment->status === 'pending' || ($appointment->status === 'confirmed' && $data['decision'] === 'declined'), 409);

        $appointment->update(['status' => $data['decision'], 'admin_note' => $data['admin_note'] ?? null]);
        $event = $data['decision'] === 'confirmed' ? AppointmentVisitorMail::CONFIRMED : AppointmentVisitorMail::DECLINED;
        $sent = $mailer->send($appointment->email, new AppointmentVisitorMail($appointment, $event), 'décision de rendez-vous');

        return back()->with($sent ? 'success' : 'error', ($data['decision'] === 'confirmed' ? 'Rendez-vous confirmé' : 'Rendez-vous refusé')
            .($sent ? ' ; le visiteur a été prévenu par e-mail.' : ', mais l\'e-mail n\'a pas pu être envoyé : prévenez le visiteur ('.$appointment->email.').'));
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $appointment->delete();

        return back()->with('success', 'Rendez-vous supprimé.');
    }

    public function editAvailability(): View
    {
        return view('admin.appointments.availability', [
            'rules'    => AvailabilityRule::orderBy('weekday')->orderBy('start_time')->get(),
            'closures' => AvailabilityClosure::where('date', '>=', today())->orderBy('date')->get(),
            'settings' => AppointmentSettings::current(),
        ]);
    }

    public function updateAvailability(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'duration'           => ['required', 'integer', 'in:15,20,30,45,60,90'],
            'notice_hours'       => ['required', 'integer', 'min:0', 'max:336'],
            'horizon_days'       => ['required', 'integer', 'min:1', 'max:180'],
            'location'           => ['required', 'string', 'max:255'],
            'topics'             => ['required', 'string', 'max:500'],
            'rules'              => ['nullable', 'array', 'max:50'],
            'rules.*.weekday'    => ['required', 'integer', 'between:1,7'],
            'rules.*.start_time' => ['required', 'date_format:H:i'],
            'rules.*.end_time'   => ['required', 'date_format:H:i', 'after:rules.*.start_time'],
            'closures'           => ['nullable', 'array', 'max:100'],
            'closures.*.date'    => ['required', 'date', 'after_or_equal:today', 'distinct'],
            'closures.*.reason'  => ['nullable', 'string', 'max:150'],
        ], [
            'rules.*.end_time.after' => 'Chaque plage doit finir après son début.',
        ], [
            'duration' => 'durée', 'notice_hours' => 'délai de prévenance', 'horizon_days' => 'horizon', 'location' => 'lieu', 'topics' => 'sujets',
        ]);

        DB::transaction(function () use ($data) {
            AppointmentSettings::save([
                'duration'     => (int) $data['duration'],
                'notice_hours' => (int) $data['notice_hours'],
                'horizon_days' => (int) $data['horizon_days'],
                'location'     => $data['location'],
                'topics'       => collect(explode(',', $data['topics']))->map(fn ($t) => trim($t))->filter()->unique()->values()->all() ?: AppointmentSettings::DEFAULTS['topics'],
            ]);

            AvailabilityRule::query()->get()->each->delete();
            foreach ($data['rules'] ?? [] as $rule) {
                AvailabilityRule::create($rule);
            }

            AvailabilityClosure::where('date', '>=', today())->get()->each->delete();
            foreach ($data['closures'] ?? [] as $closure) {
                AvailabilityClosure::create($closure);
            }
        });

        return redirect()->route('admin.appointments.availability')->with('success', 'Disponibilités enregistrées.');
    }
}
