<?php

namespace App\Services;

use App\Models\SandboxAccount;
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

    public const DEFAULTS = ['public' => false, 'internet' => false, 'auto_backup' => false, 'keep' => 7];

    /** Adresse publique transmise par l'agent (port et hôte de sandbox/.env). */
    public const SSH_SETTING = 'sandbox.ssh';

    public const DEFAULT_SSH_PORT = 2222;

    public function isConfigured(): bool
    {
        return filled(config('services.sandbox.url')) && strlen((string) config('services.sandbox.token')) >= 32;
    }

    /** @return array{public: bool, internet: bool, auto_backup: bool, keep: int} */
    public function settings(): array
    {
        return array_merge(self::DEFAULTS, (array) Setting::get(self::SETTING, []));
    }

    public function saveSettings(array $values): void
    {
        Setting::set(self::SETTING, array_merge($this->settings(), array_intersect_key($values, self::DEFAULTS)));
    }

    /** Accès public annoncé sur le site (ouvert dans le panel et agent configuré). */
    public function isOpen(): bool
    {
        return $this->isConfigured() && $this->settings()['public'];
    }

    /** État voulu envoyé à l'agent : comptes actifs uniquement. */
    public function desiredState(): array
    {
        $settings = $this->settings();

        return [
            'public'   => (bool) $settings['public'],
            'internet' => (bool) $settings['internet'],
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
        $response = $this->call('put', 'state', $this->desiredState());
        $this->rememberSsh($response['ssh'] ?? null);

        return $response['last_error'] ?? null;
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
