<?php

namespace App\Jobs;

use App\Services\SafeMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi d'un e-mail en arrière-plan (file d'attente), pour ne pas ralentir la page.
 * Trois tentatives espacées ; après le dernier échec, l'erreur est signalée dans
 * l'administration comme un échec d'envoi direct (voir App\Services\SafeMailer).
 */
class SendMail implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> Secondes entre deux tentatives */
    public array $backoff = [60, 300];

    /**
     * @param  Model|null  $markSent  élément dont la colonne $column reçoit la date d'envoi (ex. : message de contact)
     */
    public function __construct(
        public string $to,
        public Mailable $mailable,
        public string $context = 'e-mail',
        public ?Model $markSent = null,
        public string $column = 'notified_at',
    ) {
    }

    public function handle(SafeMailer $mailer): void
    {
        if ($problem = $mailer->configurationProblem()) {
            $mailer->reportFailure($this->context, $problem);

            return; // configuration invalide : réessayer ne servirait à rien
        }

        Mail::to($this->to)->send($this->mailable);

        $mailer->clearError();
        $this->markSent?->forceFill([$this->column => now()])->saveQuietly();
    }

    public function failed(Throwable $e): void
    {
        app(SafeMailer::class)->reportFailure($this->context, $e->getMessage());
    }
}
