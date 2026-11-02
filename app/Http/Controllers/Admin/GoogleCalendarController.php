<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Connexion du planning des rendez-vous à Google Agenda (OAuth 2.0). */
class GoogleCalendarController extends Controller
{
    protected const STATE = 'google-calendar.oauth-state';

    public function __construct(protected GoogleCalendar $google)
    {
    }

    /** Envoie vers l'écran d'autorisation de Google. */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->google->isConfigured(), 404);
        $state = Str::random(40);
        $request->session()->put(self::STATE, $state);

        return redirect()->away($this->google->authorizationUrl($state));
    }

    /** Retour de Google : vérification de l'état (anti-CSRF) puis échange du code. */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->google->isConfigured(), 404);
        $back = redirect()->route('admin.appointments.availability');
        $expected = $request->session()->pull(self::STATE);

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return $back->with('error', 'Connexion à Google Agenda expirée : recommencez.');
        }
        if ($request->query('error') || ! $request->query('code')) {
            return $back->with('error', 'Connexion à Google Agenda annulée.');
        }

        try {
            $this->google->connect((string) $request->query('code'));
        } catch (\Throwable $e) {
            report($e);

            return $back->with('error', 'Connexion à Google Agenda impossible : '.$e->getMessage());
        }

        return $back->with('success', 'Google Agenda connecté : vos événements bloquent désormais les créneaux correspondants.');
    }

    /** Agendas qui bloquent les créneaux, agenda qui reçoit les rendez-vous confirmés. */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->google->isConnected(), 404);
        $data = $request->validate([
            'calendars'   => ['required', 'array', 'min:1', 'max:20'],
            'calendars.*' => ['string', 'max:255'],
            'target'      => ['required', 'string', 'max:255'],
            'push'        => ['boolean'],
        ], ['calendars.required' => 'Choisissez au moins un agenda.'], ['calendars' => 'agendas', 'target' => 'agenda des rendez-vous']);

        $this->google->saveSettings(['calendars' => array_values(array_unique($data['calendars'])), 'target' => $data['target'], 'push' => $request->boolean('push')]);
        $synced = $this->google->syncBusy();

        return redirect()->route('admin.appointments.availability')->with($synced ? 'success' : 'error', $synced
            ? 'Réglages Google Agenda enregistrés et synchronisés.'
            : 'Réglages enregistrés, mais la synchronisation a échoué : '.$this->google->status()['error']);
    }

    public function sync(): RedirectResponse
    {
        abort_unless($this->google->isConnected(), 404);

        return $this->google->syncBusy()
            ? back()->with('success', 'Google Agenda synchronisé.')
            : back()->with('error', 'Synchronisation impossible : '.$this->google->status()['error']);
    }

    public function disconnect(): RedirectResponse
    {
        $this->google->disconnect();

        return back()->with('success', 'Google Agenda déconnecté. Les événements déjà créés dans votre agenda sont conservés.');
    }
}
