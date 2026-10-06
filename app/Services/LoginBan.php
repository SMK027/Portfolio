<?php

namespace App\Services;

use App\Models\IpBan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bannissement des adresses IP après des échecs de connexion répétés (fail2ban).
 *
 * Chaque échec (mot de passe, clé de sécurité, code de bot, second facteur) est
 * compté par IP sur une fenêtre glissante. Au-delà du seuil (config auth.login_ban),
 * l'IP est bannie de tout le site (App\Http\Middleware\BlockBannedIp), API comprise.
 * Bannissements, modifications et levées sont écrits dans storage/logs/security-*.log
 * et dans le journal d'activité. Les super-administrateurs les gèrent depuis le panel.
 */
class LoginBan
{
    public function __construct(protected AuditTrail $audit)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('auth.login_ban.enabled');
    }

    /**
     * Compte un échec de connexion ; bannit l'IP quand le seuil est atteint.
     */
    public function recordFailure(?string $ip, string $method): ?IpBan
    {
        if (! $this->enabled() || ! $ip) {
            return null;
        }

        $window = (int) config('auth.login_ban.window');
        $since = now()->subMinutes($window)->getTimestamp();
        $failures = array_filter((array) Cache::get($this->key($ip), []), fn ($time) => $time > $since);
        $failures[] = now()->getTimestamp();

        if (count($failures) < (int) config('auth.login_ban.max_attempts')) {
            Cache::put($this->key($ip), array_values($failures), now()->addMinutes($window));

            return null;
        }

        $this->clearFailures($ip);

        return $this->ban($ip, count($failures), $method);
    }

    /** Connexion réussie : les échecs précédents de l'IP sont oubliés. */
    public function clearFailures(?string $ip): void
    {
        if ($ip) {
            Cache::forget($this->key($ip));
        }
    }

    /** Bannissement en cours pour cette IP (la base indisponible ne bloque personne). */
    public function activeBanFor(?string $ip): ?IpBan
    {
        if (! $ip) {
            return null;
        }

        try {
            return IpBan::active()->where('ip_address', $ip)->latest('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    protected function ban(string $ip, int $attempts, string $method): IpBan
    {
        $minutes = (int) config('auth.login_ban.duration');
        $window = (int) config('auth.login_ban.window');

        // Création sans journalisation automatique : l'événement est enregistré ci-dessous.
        $ban = $this->audit->withoutRecording(fn () => IpBan::create([
            'ip_address'   => $ip,
            'attempts'     => $attempts,
            'reason'       => "{$attempts} échecs de connexion en {$window} min (dernier : {$method})",
            'banned_until' => now()->addMinutes($minutes),
        ]));

        $this->log()->warning("IP bannie : {$ip} pendant {$minutes} min ({$attempts} échecs de connexion en {$window} min, dernier : {$method})", [
            'ban_id' => $ban->id,
            'until'  => $ban->banned_until->toIso8601String(),
        ]);
        $this->audit->record('auth.ip_banned', $ban, meta: [
            'tentatives' => $attempts,
            'méthode'    => $method,
            'jusqu\'au'  => $ban->banned_until->format('d/m/Y H:i'),
        ], force: true);

        return $ban;
    }

    /** Modification par un super-administrateur (date de fin, motif). */
    public function update(IpBan $ban, ?Carbon $until, ?string $reason, ?User $by = null): void
    {
        $ban->update(['banned_until' => $until, 'reason' => $reason]);

        $this->log()->notice("Bannissement modifié : {$ban->ip_address} ".($until ? 'jusqu\'au '.$until->format('d/m/Y H:i') : 'sans date de fin'), [
            'ban_id' => $ban->id,
            'by'     => $by?->email ?? 'console',
        ]);
    }

    /** Levée anticipée par un super-administrateur (ou en console). */
    public function lift(IpBan $ban, ?User $by = null): void
    {
        $this->audit->withoutRecording(fn () => $ban->forceFill(['lifted_at' => now(), 'lifted_by' => $by?->id])->save());
        $this->clearFailures($ban->ip_address);

        $this->log()->notice("Bannissement levé : {$ban->ip_address}", [
            'ban_id' => $ban->id,
            'by'     => $by?->email ?? 'console',
        ]);
        $this->audit->record('ip_ban.lifted', $ban, force: true);
    }

    /** Bannissements terminés depuis plus de 3 mois supprimés (adresses IP = données personnelles). */
    public function prune(): int
    {
        $limit = now()->subMonths(3);

        return IpBan::query()
            ->where(fn ($q) => $q->where('lifted_at', '<', $limit)
                ->orWhere(fn ($q) => $q->whereNull('lifted_at')->where('banned_until', '<', $limit)))
            ->delete();
    }

    protected function log(): \Psr\Log\LoggerInterface
    {
        return Log::channel(config('auth.login_ban.log_channel'));
    }

    protected function key(string $ip): string
    {
        return 'login-failures:'.$ip;
    }
}
