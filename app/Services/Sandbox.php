<?php

namespace App\Services;

use App\Models\SandboxAccount;
use App\Models\SandboxConnection;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Machine SSH bac à sable : client de l'API de sandbox-agent (voir sandbox/README.md).
 * Le panel est la source de vérité (accès public, internet, comptes) ; chaque
 * modification est poussée à l'agent, qui l'applique sur la machine.
 */
class Sandbox
{
    public const SETTING = 'sandbox.settings';

    public const DEFAULTS = [
        'public'           => false,
        'internet'         => false,
        'auto_backup'      => false,
        'keep'             => 7,
        // Horaires d'ouverture au public (jours ISO 1 = lundi … 7 = dimanche, heures locales)
        'schedule_enabled' => false,
        'schedule_days'    => [1, 2, 3, 4, 5, 6, 7],
        'schedule_start'   => '09:00',
        'schedule_end'     => '20:00',
        // Ouverture temporaire (démo), même hors horaires ou accès fermé
        'open_until'       => null,
        // Coupure automatique d'internet après N minutes (0 : jamais)
        'internet_minutes' => 0,
        'internet_until'   => null,
        // Ressources de la machine et limites par compte / de connexion
        'resources'        => ['cpus' => 0.25, 'memory_mb' => 128, 'pids' => 64],
        'limits'           => ['nproc' => 24, 'memory_mb' => 192, 'file_mb' => 100, 'user_quota_mb' => 100],
        'connections'      => ['max' => 10, 'per_ip' => 2, 'per_minute' => 10],
        'motd'             => '',
        // Maintenance automatique
        'reset_frequency'  => 'none', // none | daily | weekly (lundi)
        'reference_backup' => null,
        'guest_rotation'   => 'none', // none | daily | weekly (lundi)
    ];

    public const FREQUENCIES = ['none' => 'Jamais', 'daily' => 'Chaque nuit', 'weekly' => 'Chaque lundi'];

    /** Dernier état connu pour les alertes (machine arrêtée, quota…). */
    public const MONITOR_SETTING = 'sandbox.monitor';

    /** Adresse publique transmise par l'agent (port et hôte de sandbox/.env). */
    public const SSH_SETTING = 'sandbox.ssh';

    public const DEFAULT_SSH_PORT = 2222;

    public function isConfigured(): bool
    {
        return filled(config('services.sandbox.url')) && strlen((string) config('services.sandbox.token')) >= 32;
    }

    public function settings(): array
    {
        $stored = (array) Setting::get(self::SETTING, []);
        $settings = array_merge(self::DEFAULTS, $stored);
        foreach (['resources', 'limits', 'connections'] as $group) {
            $settings[$group] = array_merge(self::DEFAULTS[$group], (array) ($stored[$group] ?? []));
        }

        return $settings;
    }

    public function saveSettings(array $values): void
    {
        Setting::set(self::SETTING, array_merge($this->settings(), array_intersect_key($values, self::DEFAULTS)));
    }

    /** Accès public annoncé sur le site : ouverture effective et agent configuré. */
    public function isOpen(): bool
    {
        return $this->isConfigured() && $this->effectivePublic();
    }

    /** Accès public en vigueur : ouverture temporaire, sinon accès ouvert et dans les horaires. */
    public function effectivePublic(?\DateTimeInterface $at = null): bool
    {
        $settings = $this->settings();
        $at = \Illuminate\Support\Carbon::instance($at ?? now())->timezone(config('app.timezone'));

        if ($settings['open_until'] && $at->lt(\Illuminate\Support\Carbon::parse($settings['open_until']))) {
            return true;
        }
        if (! $settings['public']) {
            return false;
        }
        if (! $settings['schedule_enabled']) {
            return true;
        }

        $time = $at->format('H:i');
        $start = $settings['schedule_start'];
        $end = $settings['schedule_end'];
        $inHours = $start <= $end ? ($time >= $start && $time < $end) : ($time >= $start || $time < $end); // plage sur minuit

        return in_array($at->isoWeekday(), array_map('intval', $settings['schedule_days']), true) && $inHours;
    }

    /** « Ouverte lun., mar. … de 09:00 à 20:00 », ou null sans horaires. */
    public function scheduleLabel(): ?string
    {
        $settings = $this->settings();
        if (! $settings['public'] || ! $settings['schedule_enabled'] || ! $settings['schedule_days']) {
            return null;
        }
        $names = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
        $days = collect($settings['schedule_days'])->map('intval')->sort()->map(fn ($d) => $names[$d] ?? null)->filter();

        return 'Ouverte '.($days->count() === 7 ? 'tous les jours' : 'le '.$days->join(', ', ' et ')).' de '.$settings['schedule_start'].' à '.$settings['schedule_end'].'.';
    }

    /** Internet en vigueur : activé et pas encore coupé automatiquement. */
    public function effectiveInternet(): bool
    {
        $settings = $this->settings();

        return $settings['internet'] && ! ($settings['internet_until'] && now()->gte(\Illuminate\Support\Carbon::parse($settings['internet_until'])));
    }

    /** État voulu envoyé à l'agent : comptes actifs uniquement. */
    public function desiredState(): array
    {
        $settings = $this->settings();

        return [
            'public'   => $this->effectivePublic(),
            'internet' => $this->effectiveInternet(),
            'resources' => [
                'cpus'      => (float) $settings['resources']['cpus'],
                'memory_mb' => (int) $settings['resources']['memory_mb'],
                'pids'      => (int) $settings['resources']['pids'],
            ],
            'limits' => [
                'nproc'         => (int) $settings['limits']['nproc'],
                'memory_kb'     => (int) $settings['limits']['memory_mb'] * 1024,
                'file_kb'       => (int) $settings['limits']['file_mb'] * 1024,
                'user_quota_mb' => (int) $settings['limits']['user_quota_mb'],
            ],
            'connections' => array_map('intval', $settings['connections']),
            'motd'     => (string) $settings['motd'],
            // Adresses bannies par le portfolio : refusées aussi par la machine
            'banned_ips' => \App\Models\IpBan::active()->limit(10000)->pluck('ip_address')->unique()->values()->all(),
            'accounts' => SandboxAccount::ordered()->get()->map(fn (SandboxAccount $a) => [
                'username' => $a->username,
                'hash'     => $a->password_hash,
                'enabled'  => $a->isActive(),
            ])->values()->all(),
        ];
    }

    /** Pousse l'état voulu ; renvoie l'erreur éventuelle de l'agent. */
    public function sync(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }
        // Internet coupé automatiquement : l'interrupteur du panel repasse à « coupé ».
        if ($this->settings()['internet'] && ! $this->effectiveInternet()) {
            $this->saveSettings(['internet' => false, 'internet_until' => null]);
        }
        $response = $this->call('put', 'state', $this->desiredState());
        $this->rememberSsh($response['ssh'] ?? null);

        return $response['last_error'] ?? null;
    }

    /**
     * Échecs d'authentification relevés par l'agent (SSH et terminal web) : comptés
     * par le bannissement automatique du portfolio, comme les échecs de connexion au panel.
     */
    public function processAuthFailures(): int
    {
        $failures = $this->call('post', 'auth-failures/drain')['failures'] ?? [];
        $bans = app(LoginBan::class);
        foreach ($failures as $failure) {
            // Adresse privée : passerelle Docker (proxy « userland » actif), partagée par tous les
            // visiteurs. La bannir fermerait la machine à tout le monde : ignorée.
            if (filter_var($failure['ip'] ?? null, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $bans->recordFailure($failure['ip'], ($failure['channel'] ?? '') === 'web' ? 'machine SSH (terminal web)' : 'machine SSH');
            }
        }

        return count($failures);
    }

    public function status(): array
    {
        $status = $this->call('get', 'status');
        $this->rememberSsh($status['ssh'] ?? null);

        return $status;
    }

    /** Port SSH public : .env du portfolio, sinon valeur transmise par l'agent, sinon 2222. */
    public function sshPort(): int
    {
        return (int) (config('services.sandbox.ssh_port') ?: (Setting::get(self::SSH_SETTING)['port'] ?? null) ?: self::DEFAULT_SSH_PORT);
    }

    public function sshHost(): string
    {
        return config('services.sandbox.ssh_host')
            ?: (Setting::get(self::SSH_SETTING)['host'] ?? null)
            ?: (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    /** Mémorise l'adresse publique annoncée par l'agent (enregistrée seulement si elle change). */
    protected function rememberSsh(?array $ssh): void
    {
        $port = (int) ($ssh['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            return;
        }
        $host = isset($ssh['host']) && preg_match('/^[A-Za-z0-9.-]{1,253}$/', (string) $ssh['host']) ? $ssh['host'] : null;
        $value = ['port' => $port, 'host' => $host];
        if (Setting::get(self::SSH_SETTING) !== $value) {
            Setting::set(self::SSH_SETTING, $value);
        }
    }

    public function backups(): array
    {
        return $this->call('get', 'backups')['backups'] ?? [];
    }

    public function backup(string $label, bool $automatic = false): array
    {
        return $this->call('post', 'backups', array_filter([
            'label' => $label, 'automatic' => $automatic, 'keep' => $automatic ? (int) $this->settings()['keep'] : null,
        ], fn ($v) => $v !== null), timeout: 300)['backup'];
    }

    public function restore(string $id): void
    {
        $this->call('post', 'backups/'.$this->backupId($id).'/restore', [], timeout: 300);
    }

    public function deleteBackup(string $id): void
    {
        $this->call('delete', 'backups/'.$this->backupId($id));
    }

    /** Archive d'une sauvegarde (flux). */
    public function download(string $id): Response
    {
        return $this->client(300)->withOptions(['stream' => true])->get('backups/'.$this->backupId($id).'/download')->throw();
    }

    public function killSessions(?string $username = null): void
    {
        $this->call('post', 'sessions/kill', ['username' => $username]);
    }

    /**
     * Relève l'état de la machine : historique des connexions enregistré, alertes
     * Discord sur les changements (arrêt, agent injoignable, quota, connexions refusées).
     */
    public function monitor(): void
    {
        if (! $this->isConfigured()) {
            return;
        }
        $previous = (array) Setting::get(self::MONITOR_SETTING, []);
        $discord = app(DiscordNotifier::class);

        try {
            $status = $this->status();
        } catch (RuntimeException $e) {
            if (empty($previous['unreachable'])) {
                $discord->notify('sandbox', 'Machine SSH : agent injoignable', $e->getMessage(), [], route('admin.sandbox.show'));
            }
            Setting::set(self::MONITOR_SETTING, [...$previous, 'unreachable' => true]);

            return;
        }

        $this->recordConnections($status['connections'] ?? []);

        $running = (bool) ($status['container']['running'] ?? false);
        $quota = (bool) ($status['quota']['exceeded'] ?? false);
        $refused = SandboxConnection::where('connected_at', '>=', now()->subMinutes(5))->where('result', 'like', 'refusée%')->count();
        $alerts = [
            'unreachable' => [false, null],
            'down'        => [! $running, 'Machine SSH arrêtée ('.($status['container']['status'] ?? 'inconnue').')'],
            'quota'       => [$quota, 'Machine SSH : espace disque saturé, accès public suspendu'],
            'refusals'    => [$refused >= 20, "Machine SSH : {$refused} connexions refusées en 5 minutes"],
        ];
        foreach ($alerts as $key => [$active, $title]) {
            if ($active && empty($previous[$key]) && $title) {
                $discord->notify('sandbox', $title, null, [], route('admin.sandbox.show'));
            }
        }
        Setting::set(self::MONITOR_SETTING, array_map(fn ($alert) => $alert[0], $alerts));
    }

    /** Enregistre les connexions du journal de l'agent postérieures à la dernière relevée. */
    public function recordConnections(array $connections): int
    {
        $last = SandboxConnection::max('connected_at');
        $rows = collect($connections)
            ->filter(fn ($c) => isset($c['at'], $c['result']))
            ->map(fn ($c) => [
                'connected_at' => \Illuminate\Support\Carbon::parse($c['at'])->setTimezone(config('app.timezone')),
                'ip_address'   => filter_var($c['ip'] ?? null, FILTER_VALIDATE_IP) ?: null,
                'channel'      => ($c['channel'] ?? 'ssh') === 'web' ? 'web' : 'ssh',
                'username'     => isset($c['username']) && preg_match(SandboxAccount::USERNAME_PATTERN, $c['username']) ? $c['username'] : null,
                'result'       => \Illuminate\Support\Str::limit((string) $c['result'], 80, ''),
            ])
            ->filter(fn ($row) => $last === null || $row['connected_at']->gt(\Illuminate\Support\Carbon::parse($last)))
            ->sortBy('connected_at')
            ->values();

        if ($rows->isNotEmpty()) {
            SandboxConnection::insert($rows->all());
        }

        return $rows->count();
    }

    /** Réinitialisation planifiée : restauration de la sauvegarde de référence, sinon dossiers vidés. */
    public function scheduledReset(): ?string
    {
        $settings = $this->settings();
        if (! $this->isConfigured() || ! $this->isDue($settings['reset_frequency'])) {
            return null;
        }

        if ($settings['reference_backup']) {
            $this->restore($settings['reference_backup']);
            $done = 'Machine SSH : sauvegarde de référence restaurée (réinitialisation planifiée)';
        } else {
            $this->reset();
            $done = 'Machine SSH : dossiers vidés (réinitialisation planifiée)';
        }
        app(AuditTrail::class)->record('sandbox.scheduled_reset', meta: ['sauvegarde' => $settings['reference_backup'] ?? 'aucune'], force: true);
        app(DiscordNotifier::class)->notify('sandbox', $done, null, [], route('admin.sandbox.show'));

        return $done;
    }

    /** Nouveau mot de passe pour le compte invité, selon la fréquence choisie. */
    public function rotateGuestPassword(bool $force = false): ?string
    {
        $guest = SandboxAccount::guest();
        if (! $guest || (! $force && ! $this->isDue($this->settings()['guest_rotation']))) {
            return null;
        }
        $password = SandboxAccount::generatePassword();
        $guest->setPassword($password);
        $guest->save();
        if ($this->isConfigured()) {
            try {
                $this->sync();
            } catch (RuntimeException) {
                // appliqué à la prochaine synchronisation
            }
        }

        return $password;
    }

    protected function isDue(string $frequency): bool
    {
        return $frequency === 'daily' || ($frequency === 'weekly' && now()->isMonday());
    }

    /** Mise à jour des paquets de la machine ; renvoie la fin de la sortie d'apk. */
    public function upgrade(): string
    {
        return (string) ($this->call('post', 'upgrade', [], timeout: 600)['output'] ?? '');
    }

    public function restart(): void
    {
        $this->call('post', 'restart', [], timeout: 60);
    }

    /** Dossiers personnels vidés, comptes réappliqués. */
    public function reset(): void
    {
        $this->call('post', 'reset', [], timeout: 120);
    }

    /** Sauvegarde automatique quotidienne, si elle est activée. */
    public function automaticBackup(): ?array
    {
        if (! $this->isConfigured() || ! $this->settings()['auto_backup']) {
            return null;
        }

        return $this->backup('Automatique du '.now()->format('d/m/Y H:i'), true);
    }

    /**
     * Ticket du terminal web : signé avec une clé dérivée du jeton d'API,
     * valable une minute et à usage unique (vérifié par l'agent).
     */
    public function terminalTicket(?string $ip): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode([
            'exp'   => now()->addMinute()->timestamp,
            'nonce' => Str::random(24),
            'ip'    => $ip,
        ])), '+/', '-_'), '=');
        $key = hash('sha256', 'terminal|'.config('services.sandbox.token'), true);

        return $payload.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '=');
    }

    /** Adresse du terminal web (websocket). */
    public function terminalUrl(): string
    {
        return config('services.sandbox.ws_url') ?: preg_replace('#^http#', 'ws', rtrim(config('app.url'), '/')).'/ssh-ws';
    }

    /** Commande SSH affichée aux visiteurs. */
    public function sshCommand(string $username = 'guest'): string
    {
        $host = $this->sshHost();
        $port = $this->sshPort();

        return 'ssh '.($port !== 22 ? "-p {$port} " : '').$username.'@'.$host;
    }

    protected function backupId(string $id): string
    {
        return preg_match('/^[a-f0-9]{16}$/', $id) ? $id : throw new RuntimeException('Sauvegarde invalide.');
    }

    protected function client(int $timeout = 15): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Machine SSH non configurée (SANDBOX_API_URL et SANDBOX_API_TOKEN).');
        }

        return Http::baseUrl(rtrim(config('services.sandbox.url'), '/').'/')
            ->withToken(config('services.sandbox.token'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout($timeout);
    }

    protected function call(string $method, string $path, array $data = [], int $timeout = 15): array
    {
        try {
            $response = $this->client($timeout)->{$method}($path, $data);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw new RuntimeException('Agent de la machine SSH injoignable.');
        }
        if ($response->failed()) {
            throw new RuntimeException('Agent de la machine SSH : '.($response->json('error') ?? 'erreur '.$response->status()));
        }

        return (array) $response->json();
    }
}
