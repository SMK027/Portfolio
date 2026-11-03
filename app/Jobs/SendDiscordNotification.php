<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi d'une notification à un webhook Discord, en arrière-plan.
 * Trois tentatives espacées ; Discord limite le débit (réponse 429 avec délai).
 */
class SendDiscordNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Secondes entre deux tentatives */
    public array $backoff = [30, 120];

    /** @param  ?string  $webhook  null : adresse enregistrée, relue à l'envoi (jamais stockée en clair dans la file) */
    public function __construct(public array $payload, public ?string $webhook = null)
    {
    }

    public function handle(): void
    {
        $webhook = $this->webhook ?? app(\App\Services\DiscordNotifier::class)->settings()['webhook'];
        if (! $webhook) {
            return; // webhook supprimé entre-temps
        }

        Http::timeout(5)->acceptJson()->post($webhook, $this->payload)->throw();
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Notification Discord non envoyée : '.$e->getMessage());
    }
}
