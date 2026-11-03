<?php

namespace App\Services;

use App\Jobs\SendDiscordNotification;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Notifications Discord (webhook d'un salon) pour les événements choisis dans
 * Administration → Notifications. L'URL du webhook est un secret : elle est
 * chiffrée en base. L'envoi passe par la file d'attente (voir SendDiscordNotification).
 */
class DiscordNotifier
{
    public const SETTING = 'notifications.discord';

    /** Événements notifiables : clé => [libellé, couleur de l'encadré]. */
    public const EVENTS = [
        'contact'        => ['Nouveau message de contact', 0x6366F1],
        'appointment'    => ['Nouvelle demande de rendez-vous', 0x0EA5E9],
        'cv_download'    => ['CV téléchargé avec un code d\'accès', 0x10B981],
        'recommendation' => ['Nouvelle recommandation à modérer', 0xF59E0B],
        'easter_egg'     => ['Page d\'easter eggs découverte ou élément déverrouillé', 0xEC4899],
        'ip_ban'         => ['Adresse IP bannie (échecs de connexion ou manuel)', 0xEF4444],
        'admin_login'    => ['Connexion à l\'administration', 0x64748B],
        'sandbox'        => ['Machine SSH : arrêt, agent injoignable, quota, connexions refusées, réinitialisations', 0x0F766E],
    ];

    /** Adresses de webhook acceptées (empêche d'envoyer des requêtes ailleurs). */
    public const WEBHOOK_PATTERN = '#^https://(?:(?:ptb|canary)\.)?discord(?:app)?\.com/api/webhooks/\d+/[\w-]+$#';

    /** @return array{webhook: ?string, events: list<string>} */
    public function settings(): array
    {
        $stored = (array) Setting::get(self::SETTING, []);

        return [
            'webhook' => $this->decrypt($stored['webhook'] ?? null),
            'events'  => array_values(array_intersect((array) ($stored['events'] ?? []), array_keys(self::EVENTS))),
        ];
    }

    /** @param list<string> $events  $webhook null : conserver l'adresse enregistrée */
    public function save(?string $webhook, array $events, bool $forget = false): void
    {
        $current = (array) Setting::get(self::SETTING, []);

        Setting::set(self::SETTING, [
            'webhook' => $forget ? null : ($webhook !== null ? Crypt::encryptString($webhook) : ($current['webhook'] ?? null)),
            'events'  => array_values(array_intersect($events, array_keys(self::EVENTS))),
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->settings()['webhook'] !== null;
    }

    public function wants(string $event): bool
    {
        $settings = $this->settings();

        return $settings['webhook'] !== null && in_array($event, $settings['events'], true);
    }

    /**
     * Notifie l'événement s'il est activé. Ne lève jamais d'exception : une
     * notification manquée ne doit pas faire échouer l'action du visiteur.
     *
     * @param  array<string, ?string>  $fields  libellé => valeur (valeurs vides ignorées)
     */
    public function notify(string $event, string $title, ?string $description = null, array $fields = [], ?string $url = null): void
    {
        try {
            if (! $this->wants($event)) {
                return;
            }

            SendDiscordNotification::dispatch($this->payload($event, $title, $description, $fields, $url));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Message de test, envoyé immédiatement ; renvoie l'erreur éventuelle. */
    public function test(): ?string
    {
        $webhook = $this->settings()['webhook'];
        if (! $webhook) {
            return 'Aucun webhook enregistré.';
        }

        try {
            (new SendDiscordNotification($this->payload('test', 'Notification de test', 'Les notifications du portfolio arrivent bien dans ce salon.', [], url('/')), $webhook))->handle();

            return null;
        } catch (\Throwable $e) {
            return Str::limit($e->getMessage(), 300);
        }
    }

    /** @param array<string, ?string> $fields */
    public function payload(string $event, string $title, ?string $description, array $fields, ?string $url): array
    {
        $fields = array_filter($fields, fn ($value) => $value !== null && $value !== '');

        return [
            'username'         => Str::limit(config('app.name'), 80, ''),
            'allowed_mentions' => ['parse' => []], // contenu saisi par les visiteurs : jamais de @everyone
            'embeds'           => [array_filter([
                'title'       => Str::limit($title, 250),
                'description' => $description !== null ? Str::limit($description, 1500) : null,
                'url'         => $url,
                'color'       => self::EVENTS[$event][1] ?? 0x6366F1,
                'fields'      => array_values(array_map(
                    fn ($name, $value) => ['name' => Str::limit($name, 250), 'value' => Str::limit((string) $value, 1000), 'inline' => mb_strlen((string) $value) <= 40],
                    array_keys($fields), $fields,
                )) ?: null,
                'footer'      => ['text' => self::EVENTS[$event][0] ?? 'Portfolio'],
                'timestamp'   => now()->toIso8601String(),
            ])],
        ];
    }

    protected function decrypt(?string $value): ?string
    {
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (DecryptException) {
            return null; // clé de l'application changée : webhook à ressaisir
        }
    }
}
