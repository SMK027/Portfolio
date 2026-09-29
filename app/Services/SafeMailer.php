<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Envoi d'e-mails tolérant aux pannes.
 *
 * Une panne SMTP, une configuration invalide (mailer inconnu, expéditeur
 * absent…) ou un compte de messagerie désactivé ne doit jamais faire
 * échouer une page : l'erreur est journalisée, mémorisée pour être
 * signalée dans l'administration, et l'appelant reçoit simplement false.
 */
class SafeMailer
{
    public const LAST_ERROR = 'mail.last_error';

    public const LAST_ERROR_AT = 'mail.last_error_at';

    /** Envoie un Mailable ; retourne true si l'envoi a réussi. */
    public function send(?string $to, Mailable $mailable, string $context = 'e-mail'): bool
    {
        if (blank($to) || Validator::make(['to' => $to], ['to' => 'email'])->fails()) {
            return $this->fail($context, 'destinataire absent ou invalide ('.($to ?: 'vide').')');
        }

        return $this->attempt(function () use ($to, $mailable) {
            Mail::to($to)->send($mailable);

            return true;
        }, $context);
    }

    /**
     * Exécute un envoi quelconque (notification, lien de réinitialisation…).
     * Le callback retourne true lorsqu'un e-mail a effectivement été envoyé.
     * Retourne false uniquement si une erreur d'envoi s'est produite.
     */
    public function attempt(callable $callback, string $context = 'e-mail'): bool
    {
        if ($problem = $this->configurationProblem()) {
            return $this->fail($context, $problem);
        }

        try {
            if ($callback() === true) {
                $this->clearError();
            }

            return true;
        } catch (Throwable $e) {
            return $this->fail($context, $e->getMessage());
        }
    }

    /** Détecte une configuration manifestement invalide avant toute tentative. */
    public function configurationProblem(): ?string
    {
        $mailer = config('mail.default');

        if (! $mailer || ! is_array(config("mail.mailers.$mailer"))) {
            return "mailer « {$mailer} » inconnu (MAIL_MAILER)";
        }

        $from = config('mail.from.address');
        if (blank($from) || Validator::make(['from' => $from], ['from' => 'email'])->fails()) {
            return 'adresse d\'expédition absente ou invalide (MAIL_FROM_ADDRESS)';
        }

        return null;
    }

    /** Dernière erreur d'envoi non résolue, pour l'administration. */
    public function lastError(): ?array
    {
        try {
            $message = Setting::get(self::LAST_ERROR);
        } catch (Throwable) {
            return null;
        }

        return $message ? ['message' => $message, 'at' => Setting::get(self::LAST_ERROR_AT)] : null;
    }

    public function clearError(): void
    {
        try {
            if (Setting::get(self::LAST_ERROR)) {
                Setting::set(self::LAST_ERROR, null);
                Setting::set(self::LAST_ERROR_AT, null);
            }
        } catch (Throwable) {
            // La base est indisponible : rien à nettoyer.
        }
    }

    protected function fail(string $context, string $reason): bool
    {
        $reason = mb_substr(trim($reason), 0, 500);
        Log::error("Échec d'envoi ({$context}) : {$reason}");

        try {
            Setting::set(self::LAST_ERROR, ucfirst($context).' : '.$reason);
            Setting::set(self::LAST_ERROR_AT, now()->toIso8601String());
        } catch (Throwable) {
            // Ne jamais propager une erreur secondaire.
        }

        return false;
    }
}
