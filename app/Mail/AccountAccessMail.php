<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Accès au compte : bienvenue, invitation à choisir son mot de passe, lien de
 * réinitialisation ou avis de changement. Aucun mot de passe n'est jamais envoyé.
 */
class AccountAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Compte créé, mot de passe fixé par l'administrateur (communiqué à part). */
    public const WELCOME = 'welcome';

    /** Compte créé, la personne choisit son mot de passe via le lien. */
    public const INVITATION = 'invitation';

    /** Lien de réinitialisation (administrateur ou « mot de passe oublié »). */
    public const RESET = 'reset';

    /** Mot de passe modifié par un administrateur. */
    public const PASSWORD_CHANGED = 'password_changed';

    /**
     * @param  string|null  $url  lien pour choisir le mot de passe (invitation, réinitialisation)
     * @param  int|null  $expiresIn  validité du lien, en minutes
     */
    public function __construct(
        public User $account,
        public string $event,
        public ?string $url = null,
        public ?int $expiresIn = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $app = config('app.name');

        return new Envelope(subject: match ($this->event) {
            self::WELCOME          => "Votre compte {$app} a été créé",
            self::INVITATION       => "Activez votre compte {$app}",
            self::RESET            => "Réinitialisation de votre mot de passe {$app}",
            default                => "Votre mot de passe {$app} a été modifié",
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.account-access', with: ['expiry' => $this->expiryLabel()]);
    }

    /** Validité du lien en clair : « 72 heures », « 60 minutes ». */
    public function expiryLabel(): ?string
    {
        if (! $this->expiresIn) {
            return null;
        }

        return $this->expiresIn % 60 === 0
            ? trans_choice(':count heure|:count heures', $this->expiresIn / 60)
            : trans_choice(':count minute|:count minutes', $this->expiresIn);
    }
}
