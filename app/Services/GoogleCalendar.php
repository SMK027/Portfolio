<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\GoogleBusyPeriod;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Connexion du planning des rendez-vous à Google Agenda (OAuth 2.0, API REST v3) :
 *   - les périodes occupées des agendas choisis bloquent les créneaux (freeBusy) ;
 *   - les rendez-vous confirmés sont ajoutés à l'agenda cible, et retirés s'ils sont annulés.
 *
 * Identifiants OAuth : GOOGLE_CALENDAR_CLIENT_ID / GOOGLE_CALENDAR_CLIENT_SECRET.
 */
class GoogleCalendar
{
    /** Réglages (dans « settings ») : jeton de rafraîchissement chiffré, compte, agendas. */
    public const KEY = 'appointments.google';

    /** Dernière synchronisation (cache : pas de bruit dans le journal d'activité). */
    public const STATUS_KEY = 'google-calendar:status';

    protected const TOKEN_CACHE = 'google-calendar:access-token';

    public const SCOPES = [
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
        'https://www.googleapis.com/auth/calendar.freebusy',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    protected const API = 'https://www.googleapis.com/calendar/v3';

    public function isConfigured(): bool
    {
        return filled(config('services.google_calendar.client_id')) && filled(config('services.google_calendar.client_secret'));
    }

    public function isConnected(): bool
    {
        return $this->isConfigured() && filled($this->settings()['token']);
    }

    /** @return array{token: ?string, account: ?string, calendars: list<string>, target: string, push: bool} */
    public function settings(): array
    {
        return array_merge(
            ['token' => null, 'account' => null, 'calendars' => ['primary'], 'target' => 'primary', 'push' => true],
            (array) Setting::get(self::KEY, []),
        );
    }

    public function saveSettings(array $values): void
    {
        Setting::set(self::KEY, array_merge($this->settings(), $values));
    }

    /** @return array{synced_at: ?string, error: ?string} */
    public function status(): array
    {
        return array_merge(['synced_at' => null, 'error' => null], (array) Cache::get(self::STATUS_KEY, []));
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id'     => config('services.google_calendar.client_id'),
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'access_type'   => 'offline',
            'prompt'        => 'consent', // garantit un jeton de rafraîchissement
            'include_granted_scopes' => 'true',
            'state'         => $state,
        ]);
    }

    public function redirectUri(): string
    {
        return route('admin.appointments.google.callback');
    }

    /** Échange le code d'autorisation contre les jetons, puis mémorise le compte connecté. */
    public function connect(string $code): void
    {
        $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',
        ]);
        if (! $response->successful() || ! $response->json('refresh_token')) {
            throw new RuntimeException('Google a refusé la connexion ('.($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status()).').');
        }

        Cache::put(self::TOKEN_CACHE, $response->json('access_token'), max(60, (int) $response->json('expires_in', 3600) - 120));
        $this->saveSettings(['token' => Crypt::encryptString($response->json('refresh_token')), 'account' => null, 'calendars' => ['primary'], 'target' => 'primary']);

        $primary = collect($this->calendars())->firstWhere('primary', true);
        $this->saveSettings(['account' => $primary['id'] ?? null]);
        $this->syncBusy();
    }

    /** Révoque l'accès chez Google et efface les données importées. */
    public function disconnect(): void
    {
        if ($token = $this->refreshToken()) {
            rescue(fn () => Http::asForm()->timeout(5)->post('https://oauth2.googleapis.com/revoke', ['token' => $token]), report: false);
        }
        $this->saveSettings(['token' => null, 'account' => null]);
        Cache::forget(self::TOKEN_CACHE);
        Cache::forget(self::STATUS_KEY);
        GoogleBusyPeriod::query()->delete();
        Appointment::whereNotNull('google_event_id')->update(['google_event_id' => null]);
    }

    /**
     * Agendas du compte (pour choisir ceux qui bloquent les créneaux et celui qui reçoit les rendez-vous).
     *
     * @return list<array{id: string, name: string, primary: bool, writable: bool}>
     */
    public function calendars(): array
    {
        $items = $this->api()->get(self::API.'/users/me/calendarList', ['minAccessRole' => 'freeBusyReader'])->throw()->json('items', []);

        return collect($items)->map(fn (array $c) => [
            'id'       => $c['id'],
            'name'     => $c['summaryOverride'] ?? $c['summary'] ?? $c['id'],
            'primary'  => (bool) ($c['primary'] ?? false),
            'writable' => in_array($c['accessRole'] ?? '', ['owner', 'writer'], true),
        ])->sortByDesc('primary')->values()->all();
    }

    /**
     * Importe les périodes occupées des agendas choisis, sur l'horizon de réservation.
     * En cas d'échec, les périodes précédentes sont conservées et l'erreur affichée dans l'administration.
     */
    public function syncBusy(): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        try {
            $from = CarbonImmutable::today();
            $to = $from->addDays((int) AppointmentSettings::current()['horizon_days'] + 1);
            $calendars = $this->settings()['calendars'] ?: ['primary'];

            $response = $this->api()->post(self::API.'/freeBusy', [
                'timeMin'  => $from->toRfc3339String(),
                'timeMax'  => $to->toRfc3339String(),
                'timeZone' => config('app.timezone'),
                'items'    => array_map(fn ($id) => ['id' => $id], $calendars),
            ])->throw();

            $periods = [];
            foreach ((array) $response->json('calendars', []) as $id => $calendar) {
                if (! empty($calendar['errors'])) {
                    throw new RuntimeException('agenda « '.$id.' » inaccessible ('.($calendar['errors'][0]['reason'] ?? 'erreur').')');
                }
                foreach ($calendar['busy'] ?? [] as $busy) {
                    $periods[] = [
                        'starts_at' => CarbonImmutable::parse($busy['start'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                        'ends_at'   => CarbonImmutable::parse($busy['end'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    ];
                }
            }

            DB::transaction(function () use ($periods) {
                GoogleBusyPeriod::query()->delete();
                GoogleBusyPeriod::insert($periods);
            });
            Cache::forever(self::STATUS_KEY, ['synced_at' => now()->toIso8601String(), 'error' => null]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Google Agenda : synchronisation impossible — '.$e->getMessage());
            Cache::forever(self::STATUS_KEY, ['synced_at' => $this->status()['synced_at'], 'error' => $this->describe($e)]);

            return false;
        }
    }

    /** Crée ou met à jour l'événement Google d'un rendez-vous confirmé. */
    public function pushAppointment(Appointment $appointment): void
    {
        if (! $this->settings()['push']) {
            return;
        }

        $event = [
            'summary'     => 'Rendez-vous : '.$appointment->name.' — '.$appointment->topic,
            'location'    => (string) AppointmentSettings::current()['location'],
            'description' => implode("\n", array_filter([
                'E-mail : '.$appointment->email,
                $appointment->phone ? 'Téléphone : '.$appointment->phone : null,
                $appointment->message ? "\n".$appointment->message : null,
                "\n".route('admin.appointments.index'),
            ])),
            'start'       => ['dateTime' => $appointment->starts_at->toRfc3339String(), 'timeZone' => config('app.timezone')],
            'end'         => ['dateTime' => $appointment->ends_at->toRfc3339String(), 'timeZone' => config('app.timezone')],
            'attendees'   => [['email' => $appointment->email, 'displayName' => $appointment->name]],
            'reminders'   => ['useDefault' => true],
        ];
        $url = self::API.'/calendars/'.rawurlencode($this->settings()['target']).'/events';

        // sendUpdates=none : le visiteur reçoit déjà nos propres e-mails (et l'invitation .ics).
        $response = $appointment->google_event_id
            ? $this->api()->patch($url.'/'.rawurlencode($appointment->google_event_id).'?sendUpdates=none', $event)
            : null;
        if (! $response || in_array($response->status(), [404, 410], true)) {
            $response = $this->api()->post($url.'?sendUpdates=none', $event);
        }

        $appointment->forceFill(['google_event_id' => $response->throw()->json('id')])->saveQuietly();
    }

    public function deleteEvent(string $eventId): void
    {
        $response = $this->api()->delete(self::API.'/calendars/'.rawurlencode($this->settings()['target']).'/events/'.rawurlencode($eventId).'?sendUpdates=none');
        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }
    }

    protected function api(): PendingRequest
    {
        return Http::timeout(10)->connectTimeout(5)->acceptJson()->withToken($this->accessToken());
    }

    protected function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE);
        if ($cached) {
            return $cached;
        }

        $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'refresh_token' => $this->refreshToken() ?? throw new RuntimeException('Google Agenda n\'est pas connecté.'),
            'grant_type'    => 'refresh_token',
        ]);
        if ($response->json('error') === 'invalid_grant') {
            throw new RuntimeException('l\'accès a été révoqué ou a expiré : reconnectez Google Agenda.');
        }
        $token = $response->throw()->json('access_token');
        Cache::put(self::TOKEN_CACHE, $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }

    protected function refreshToken(): ?string
    {
        $token = $this->settings()['token'];

        return $token ? rescue(fn () => Crypt::decryptString($token), null, report: false) : null;
    }

    protected function describe(\Throwable $e): string
    {
        $response = $e instanceof \Illuminate\Http\Client\RequestException ? $e->response : null;

        return $response instanceof Response
            ? 'Google a répondu HTTP '.$response->status().($response->json('error.message') ? ' : '.$response->json('error.message') : '')
            : $e->getMessage();
    }
}
