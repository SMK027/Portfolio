<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AppointmentVisitorMail;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\AvailabilityClosure;
use App\Models\AvailabilityRule;
use App\Models\AvailabilitySlot;
use App\Services\SafeMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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

    /** Calendrier des disponibilités (FullCalendar) et réglages. */
    public function editAvailability(): View
    {
        return view('admin.appointments.availability', ['settings' => AppointmentSettings::current()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'duration'     => ['required', 'integer', 'in:15,20,30,45,60,90'],
            'notice_hours' => ['required', 'integer', 'min:0', 'max:336'],
            'horizon_days' => ['required', 'integer', 'min:1', 'max:180'],
            'location'     => ['required', 'string', 'max:255'],
            'topics'       => ['required', 'string', 'max:500'],
        ], [], ['duration' => 'durée', 'notice_hours' => 'délai de prévenance', 'horizon_days' => 'horizon', 'location' => 'lieu', 'topics' => 'sujets']);

        AppointmentSettings::save([
            'duration'     => (int) $data['duration'],
            'notice_hours' => (int) $data['notice_hours'],
            'horizon_days' => (int) $data['horizon_days'],
            'location'     => $data['location'],
            'topics'       => collect(explode(',', $data['topics']))->map(fn ($t) => trim($t))->filter()->unique()->values()->all() ?: AppointmentSettings::DEFAULTS['topics'],
        ]);

        return redirect()->route('admin.appointments.availability')->with('success', 'Réglages enregistrés.');
    }

    /**
     * Événements affichés par FullCalendar sur la période demandée :
     * plages hebdomadaires (récurrentes), plages ponctuelles, jours fermés, rendez-vous.
     */
    public function events(Request $request): JsonResponse
    {
        $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date']]);
        $start = Carbon::parse($request->query('start'));
        $end = Carbon::parse($request->query('end'));
        $status = ['pending' => ['#f59e0b', 'En attente'], 'confirmed' => ['#10b981', 'Confirmé']];

        return response()->json([
            ...AvailabilityRule::all()->map(fn (AvailabilityRule $rule) => [
                'id'         => 'weekly-'.$rule->id,
                'title'      => 'Chaque semaine',
                'daysOfWeek' => [$rule->weekday % 7],
                'startTime'  => substr($rule->start_time, 0, 5),
                'endTime'    => substr($rule->end_time, 0, 5),
                'classNames' => ['availability', 'availability-weekly'],
                'extendedProps' => ['kind' => 'weekly', 'key' => $rule->id],
            ]),
            ...AvailabilitySlot::where('ends_at', '>', $start)->where('starts_at', '<', $end)->get()->map(fn (AvailabilitySlot $slot) => [
                'id'         => 'date-'.$slot->id,
                'title'      => 'Ce jour uniquement',
                'start'      => $slot->starts_at->format('Y-m-d\TH:i:s'),
                'end'        => $slot->ends_at->format('Y-m-d\TH:i:s'),
                'classNames' => ['availability', 'availability-date'],
                'extendedProps' => ['kind' => 'date', 'key' => $slot->id],
            ]),
            ...AvailabilityClosure::whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()->map(fn (AvailabilityClosure $closure) => [
                'id'         => 'closed-'.$closure->id,
                'title'      => 'Fermé'.($closure->reason ? ' — '.$closure->reason : ''),
                'start'      => $closure->date->toDateString(),
                'allDay'     => true,
                'display'    => 'background',
                'classNames' => ['availability-closed'],
                'extendedProps' => ['kind' => 'closure', 'key' => $closure->id],
            ]),
            ...Appointment::holding()->where('ends_at', '>', $start)->where('starts_at', '<', $end)->get()->map(fn (Appointment $appointment) => [
                'id'         => 'appointment-'.$appointment->id,
                'title'      => $appointment->name.' — '.$appointment->topic,
                'start'      => $appointment->starts_at->format('Y-m-d\TH:i:s'),
                'end'        => $appointment->ends_at->format('Y-m-d\TH:i:s'),
                'color'      => $status[$appointment->status][0],
                'editable'   => false,
                'classNames' => ['appointment'],
                'extendedProps' => ['kind' => 'appointment', 'status' => $status[$appointment->status][1]],
            ]),
        ]);
    }

    /** Nouvelle plage : chaque semaine (même jour) ou uniquement à cette date. */
    public function storeRange(Request $request): JsonResponse
    {
        [$kind, $start, $end] = $this->validatedRange($request);

        $kind === 'weekly'
            ? AvailabilityRule::create(['weekday' => $start->isoWeekday(), 'start_time' => $start->format('H:i'), 'end_time' => $end->format('H:i')])
            : AvailabilitySlot::create(['starts_at' => $start, 'ends_at' => $end]);

        return response()->json(['ok' => true], 201);
    }

    /** Plage déplacée ou redimensionnée dans le calendrier. */
    public function updateRange(Request $request, string $kind, int $id): JsonResponse
    {
        abort_unless(in_array($kind, ['weekly', 'date'], true), 404);
        [, $start, $end] = $this->validatedRange($request->merge(['kind' => $kind]));

        $kind === 'weekly'
            ? AvailabilityRule::findOrFail($id)->update(['weekday' => $start->isoWeekday(), 'start_time' => $start->format('H:i'), 'end_time' => $end->format('H:i')])
            : AvailabilitySlot::findOrFail($id)->update(['starts_at' => $start, 'ends_at' => $end]);

        return response()->json(['ok' => true]);
    }

    public function destroyRange(string $kind, int $id): JsonResponse
    {
        abort_unless(in_array($kind, ['weekly', 'date'], true), 404);
        ($kind === 'weekly' ? AvailabilityRule::findOrFail($id) : AvailabilitySlot::findOrFail($id))->delete();

        return response()->json(['ok' => true]);
    }

    /** Ferme ou rouvre une journée (clic sur l'en-tête du jour). */
    public function toggleClosure(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'reason' => ['nullable', 'string', 'max:150']]);
        $closure = AvailabilityClosure::whereDate('date', $data['date'])->first();

        $closure ? $closure->delete() : AvailabilityClosure::create($data);

        return response()->json(['closed' => ! $closure]);
    }

    /** @return array{0: string, 1: Carbon, 2: Carbon} */
    protected function validatedRange(Request $request): array
    {
        $data = $request->validate([
            'kind'  => ['required', 'in:weekly,date'],
            'start' => ['required', 'date'],
            'end'   => ['required', 'date', 'after:start'],
        ], ['end.after' => 'La plage doit finir après son début.']);

        $start = Carbon::parse($data['start'])->setTimezone(config('app.timezone'))->seconds(0);
        $end = Carbon::parse($data['end'])->setTimezone(config('app.timezone'))->seconds(0);
        if (! $start->isSameDay($end) && ! ($end->isStartOfDay() && $end->copy()->subDay()->isSameDay($start))) {
            throw ValidationException::withMessages(['end' => 'Une plage doit tenir dans une seule journée.']);
        }
        if ($end->isStartOfDay()) {
            $end = $start->copy()->setTime(23, 59);
        }

        return [$data['kind'], $start, $end];
    }
}
