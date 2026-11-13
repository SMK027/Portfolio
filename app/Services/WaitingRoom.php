<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WaitingRoomTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * File d'attente du site public en cas de forte affluence.
 *
 * Chaque visiteur reçoit un ticket (cookie). Au plus « capacity » visiteurs
 * naviguent en même temps ; les suivants patientent sur une page qui
 * s'actualise et entrent dans l'ordre d'arrivée dès qu'une place se libère.
 * Une place se libère après « session_minutes » d'inactivité du visiteur admis.
 *
 * Modes :
 * - off    : aucune file, aucun suivi ;
 * - auto   : file déclenchée d'elle-même quand la capacité est atteinte,
 *            et levée dès que tout le monde est entré et qu'il reste de la place ;
 * - manual : file imposée à tous les nouveaux visiteurs (fin automatique facultative).
 *
 * En mode automatique, la file se déclenche aussi quand le serveur peine
 * (temps de réponse moyen ou charge par cœur au-delà des seuils facultatifs) :
 * les admissions sont alors suspendues jusqu'au retour à la normale.
 *
 * Les personnes connectées ne sont jamais mises en attente.
 */
class WaitingRoom
{
    public const SETTINGS = 'waiting_room.settings';

    public const COOKIE = 'wr_ticket';

    public const MODES = ['off' => 'Désactivée', 'auto' => 'Automatique', 'manual' => 'Manuelle (forcée)'];

    public const DEFAULTS = [
        'mode'            => 'off',
        'capacity'        => 100,
        'session_minutes' => 5,
        'ends_at'         => null,
        'message'         => null,
        'max_response_ms' => null,
        'max_load'        => null,
    ];

    /** Déclenchement automatique en cours (pour les notifications). */
    protected const TRIGGERED = 'waiting-room:triggered';

    /** Temps de réponse moyen récent : [moyenne en ms, horodatage]. */
    protected const RESPONSE_TIME = 'waiting-room:response-time';

    /** Une mesure plus ancienne est ignorée (plus de trafic admis pour la renouveler). */
    protected const RESPONSE_TIME_TTL = 60;

    /** Un visiteur en attente qui ne s'est pas manifesté depuis ce délai a quitté la page. */
    public const ABANDON_SECONDS = 90;

    /** Délai entre deux mises à jour de « last_seen_at » (limite les écritures). */
    protected const TOUCH_SECONDS = 30;

    /** Règles de validation des réglages (administration et API). */
    public static function rules(): array
    {
        return [
            'mode'            => ['required', \Illuminate\Validation\Rule::in(array_keys(self::MODES))],
            'capacity'        => ['required', 'integer', 'min:1', 'max:100000'],
            'session_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'ends_at'         => ['nullable', 'date', 'after:now'],
            'message'         => ['nullable', 'string', 'max:1000'],
            'max_response_ms' => ['nullable', 'integer', 'min:50', 'max:60000'],
            'max_load'        => ['nullable', 'numeric', 'min:0.1', 'max:50'],
        ];
    }

    /** Réglages validés → valeurs enregistrées. */
    public static function normalize(array $data): array
    {
        return [
            'mode'            => $data['mode'],
            'capacity'        => (int) $data['capacity'],
            'session_minutes' => (int) $data['session_minutes'],
            'ends_at'         => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at'])->toIso8601String() : null,
            'message'         => $data['message'] ?? null,
            'max_response_ms' => filled($data['max_response_ms'] ?? null) ? (int) $data['max_response_ms'] : null,
            'max_load'        => filled($data['max_load'] ?? null) ? round((float) $data['max_load'], 2) : null,
        ];
    }

    /** État exposé (administration et API). */
    public function status(): array
    {
        $enabled = $this->isEnabled();

        return [
            'queueing'         => $enabled && $this->isQueueing(),
            'overloaded'       => $this->isOverloaded(),
            'admitted'         => $enabled ? $this->admittedCount() : 0,
            'waiting'          => $enabled ? $this->waitingCount() : 0,
            'queueing_since'   => $this->queueingSince(),
            'response_time_ms' => ($ms = $this->averageResponseTime()) !== null ? (int) round($ms) : null,
            'load_per_core'    => $this->loadPerCore(),
        ];
    }

    public function settings(): array
    {
        $settings = array_merge(self::DEFAULTS, array_intersect_key((array) Setting::get(self::SETTINGS, []), self::DEFAULTS));

        // File manuelle arrivée à sa date de fin : retour au mode automatique.
        if ($settings['mode'] === 'manual' && $settings['ends_at'] && Carbon::parse($settings['ends_at'])->isPast()) {
            $settings = array_merge($settings, ['mode' => 'auto', 'ends_at' => null]);
            Setting::set(self::SETTINGS, $settings);
        }

        return $settings;
    }

    public function update(array $settings): void
    {
        $settings = array_merge($this->settings(), array_intersect_key($settings, self::DEFAULTS));
        if ($settings['mode'] !== 'manual') {
            $settings['ends_at'] = null;
        }

        Setting::set(self::SETTINGS, $settings);

        if ($settings['mode'] !== 'auto') {
            Cache::forget(self::TRIGGERED);
        }

        if ($settings['mode'] === 'off') {
            WaitingRoomTicket::query()->delete();
        }
    }

    public function mode(): string
    {
        return $this->settings()['mode'];
    }

    public function isEnabled(): bool
    {
        return $this->mode() !== 'off';
    }

    public function capacity(): int
    {
        return max(1, (int) $this->settings()['capacity']);
    }

    public function endsAt(): ?Carbon
    {
        $value = $this->settings()['ends_at'];

        return $value ? Carbon::parse($value) : null;
    }

    public function message(): ?string
    {
        return $this->settings()['message'] ?: null;
    }

    /** Les nouveaux visiteurs doivent-ils patienter ? */
    public function isQueueing(): bool
    {
        return match ($this->mode()) {
            'manual' => true,
            'auto'   => $this->waitingCount() > 0 || $this->admittedCount() >= $this->capacity() || $this->isOverloaded(),
            default  => false,
        };
    }

    public function admittedCount(): int
    {
        return WaitingRoomTicket::admitted()->where('last_seen_at', '>=', $this->admittedCutoff())->count();
    }

    public function waitingCount(): int
    {
        return WaitingRoomTicket::waiting()->where('last_seen_at', '>=', $this->waitingCutoff())->count();
    }

    /** Date d'arrivée du plus ancien visiteur en attente (début de la saturation). */
    public function queueingSince(): ?Carbon
    {
        $since = WaitingRoomTicket::waiting()->where('last_seen_at', '>=', $this->waitingCutoff())->min('created_at');

        return $since ? Carbon::parse($since) : null;
    }

    public function find(?string $token): ?WaitingRoomTicket
    {
        if (! is_string($token) || strlen($token) !== 40) {
            return null;
        }

        return WaitingRoomTicket::where('token', $token)->first();
    }

    public function issue(bool $admitted): WaitingRoomTicket
    {
        return WaitingRoomTicket::create([
            'token'        => Str::random(40),
            'admitted_at'  => $admitted ? now() : null,
            'last_seen_at' => now(),
        ]);
    }

    /** Signale l'activité du visiteur (au plus toutes les 30 s). */
    public function touch(WaitingRoomTicket $ticket): void
    {
        if ($ticket->last_seen_at->lt(now()->subSeconds(self::TOUCH_SECONDS))) {
            $ticket->forceFill(['last_seen_at' => now()])->save();
        }
    }

    /** Rang du visiteur dans la file (1 = le prochain à entrer). */
    public function position(WaitingRoomTicket $ticket): int
    {
        return WaitingRoomTicket::waiting()
            ->where('last_seen_at', '>=', $this->waitingCutoff())
            ->where('id', '<=', $ticket->id)
            ->count();
    }

    /** Estimation grossière de l'attente, en minutes. */
    public function estimatedMinutes(int $position): int
    {
        return (int) max(1, ceil($position / $this->capacity() * $this->settings()['session_minutes']));
    }

    /**
     * Libère les places des visiteurs inactifs et fait entrer les suivants,
     * dans l'ordre d'arrivée. Un verrou évite les admissions concurrentes.
     */
    public function advance(): void
    {
        Cache::lock('waiting-room:advance', 10)->get(function () {
            $this->prune();

            $this->checkLifted();

            $free = $this->isOverloaded() ? 0 : $this->capacity() - $this->admittedCount();
            if ($free <= 0) {
                return;
            }

            $ids = WaitingRoomTicket::waiting()->orderBy('id')->limit($free)->pluck('id');
            if ($ids->isNotEmpty()) {
                WaitingRoomTicket::whereIn('id', $ids)->update(['admitted_at' => now()]);
            }
        });
    }

    /**
     * Mesure d'une requête admise (moyenne glissante). Échantillonnée : une
     * requête sur cinq, pour limiter les écritures dans le cache.
     */
    public function recordResponseTime(float $milliseconds): void
    {
        if (! $this->settings()['max_response_ms'] || random_int(1, 5) !== 1) {
            return;
        }

        [$average, $at] = Cache::get(self::RESPONSE_TIME, [null, 0]);
        $average = $average === null || $at < now()->timestamp - self::RESPONSE_TIME_TTL
            ? $milliseconds
            : $average * 0.8 + $milliseconds * 0.2;

        Cache::put(self::RESPONSE_TIME, [$average, now()->timestamp], self::RESPONSE_TIME_TTL * 2);
    }

    public function averageResponseTime(): ?float
    {
        [$average, $at] = Cache::get(self::RESPONSE_TIME, [null, 0]);

        return $at >= now()->timestamp - self::RESPONSE_TIME_TTL ? $average : null;
    }

    /** Charge moyenne sur 1 minute rapportée au nombre de cœurs (null si indisponible). */
    public function loadPerCore(): ?float
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        if (! $load) {
            return null;
        }

        return round($load[0] / max(1, $this->cpuCount()), 2);
    }

    /** Le serveur dépasse-t-il un des seuils de charge réglés ? */
    public function isOverloaded(): bool
    {
        if ($this->mode() !== 'auto') {
            return false;
        }

        $settings = $this->settings();
        $response = $this->averageResponseTime();
        $load = $settings['max_load'] ? $this->loadPerCore() : null;

        return ($settings['max_response_ms'] && $response !== null && $response > $settings['max_response_ms'])
            || ($settings['max_load'] && $load !== null && $load > $settings['max_load']);
    }

    /** Un visiteur vient d'être mis en attente : notification au premier déclenchement automatique. */
    public function markTriggered(): void
    {
        if ($this->mode() === 'auto' && Cache::add(self::TRIGGERED, now()->timestamp, now()->addDay())) {
            app(DiscordNotifier::class)->notify('site_status', 'File d\'attente déclenchée',
                $this->isOverloaded() ? 'Le serveur est surchargé : les nouveaux visiteurs patientent.' : 'La capacité est atteinte : les nouveaux visiteurs patientent.',
                ['Visiteurs sur le site' => (string) $this->admittedCount(), 'Capacité' => (string) $this->capacity()],
                route('admin.waiting-room.edit'));
        }
    }

    /** Fin d'un déclenchement automatique : notification. */
    protected function checkLifted(): void
    {
        $since = Cache::get(self::TRIGGERED);
        if ($since === null || ($this->mode() === 'auto' && $this->isQueueing())) {
            return;
        }

        Cache::forget(self::TRIGGERED);
        app(DiscordNotifier::class)->notify('site_status', 'File d\'attente levée',
            'Tous les visiteurs sont entrés : le site est de nouveau en accès libre.',
            ['Durée' => Carbon::createFromTimestamp($since)->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)],
            route('admin.waiting-room.edit'));
    }

    protected function cpuCount(): int
    {
        return Cache::rememberForever('waiting-room:cpus', function () {
            $info = @file_get_contents('/proc/cpuinfo');

            return $info ? max(1, substr_count($info, "\nprocessor") + (str_starts_with($info, 'processor') ? 1 : 0)) : 1;
        });
    }

    /** Fait entrer immédiatement tous les visiteurs en attente. */
    public function admitAll(): int
    {
        return WaitingRoomTicket::waiting()->update(['admitted_at' => now(), 'last_seen_at' => now()]);
    }

    /** Supprime les tickets expirés (visiteurs inactifs ou partis). */
    public function prune(): void
    {
        if (! $this->isEnabled()) {
            WaitingRoomTicket::query()->delete();

            return;
        }

        DB::transaction(function () {
            WaitingRoomTicket::admitted()->where('last_seen_at', '<', $this->admittedCutoff())->delete();
            WaitingRoomTicket::waiting()->where('last_seen_at', '<', $this->waitingCutoff())->delete();
        });
    }

    protected function admittedCutoff(): Carbon
    {
        return now()->subMinutes(max(1, (int) $this->settings()['session_minutes']));
    }

    protected function waitingCutoff(): Carbon
    {
        return now()->subSeconds(self::ABANDON_SECONDS);
    }
}
