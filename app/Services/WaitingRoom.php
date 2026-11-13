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
    ];

    /** Un visiteur en attente qui ne s'est pas manifesté depuis ce délai a quitté la page. */
    public const ABANDON_SECONDS = 90;

    /** Délai entre deux mises à jour de « last_seen_at » (limite les écritures). */
    protected const TOUCH_SECONDS = 30;

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
            'auto'   => $this->waitingCount() > 0 || $this->admittedCount() >= $this->capacity(),
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

            $free = $this->capacity() - $this->admittedCount();
            if ($free <= 0) {
                return;
            }

            $ids = WaitingRoomTicket::waiting()->orderBy('id')->limit($free)->pluck('id');
            if ($ids->isNotEmpty()) {
                WaitingRoomTicket::whereIn('id', $ids)->update(['admitted_at' => now()]);
            }
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
