<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vérification côté serveur d'un jeton Google reCAPTCHA v3.
 */
class Recaptcha
{
    /** Score obtenu lors de la dernière vérification réussie. */
    protected ?float $lastScore = null;

    public function siteKey(): ?string
    {
        return config('services.recaptcha.site_key') ?: null;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.recaptcha.site_key')) && filled(config('services.recaptcha.secret_key'));
    }

    public function lastScore(): ?float
    {
        return $this->lastScore;
    }

    /**
     * Vérifie le jeton pour l'action attendue.
     * Sans clés configurées, la vérification est ignorée hors production.
     */
    public function verify(?string $token, string $action, ?string $ip = null): bool
    {
        $this->lastScore = null;

        if (! $this->isConfigured()) {
            if (app()->environment('local', 'testing')) {
                Log::warning('reCAPTCHA non configuré : vérification ignorée (environnement '.app()->environment().').');

                return true;
            }

            Log::error('reCAPTCHA non configuré : soumission refusée.');

            return false;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $result = Http::asForm()
                ->timeout(5)
                ->post(config('services.recaptcha.verify_url'), array_filter([
                    'secret'   => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]))
                ->json();
        } catch (Throwable $e) {
            Log::error('reCAPTCHA injoignable : '.$e->getMessage());

            return false;
        }

        $score = isset($result['score']) ? (float) $result['score'] : 0.0;

        $valid = ($result['success'] ?? false) === true
            && ($result['action'] ?? null) === $action
            && $score >= (float) config('services.recaptcha.min_score', 0.5);

        if ($valid) {
            $this->lastScore = $score;
        }

        return $valid;
    }
}
