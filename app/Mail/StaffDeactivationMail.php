<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Désactivation programmée d'un compte du personnel : avertissement à la personne
 * quelques jours avant, puis notification aux super-administrateurs le jour même.
 */
class StaffDeactivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const WARNING = 'warning';

    public const DEACTIVATED = 'deactivated';

    public function __construct(public User $account, public string $event)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->event === self::WARNING
            ? 'Votre accès sera désactivé le '.$this->account->deactivates_at->translatedFormat('j F à H:i')
            : 'Compte désactivé : '.$this->account->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.staff-deactivation');
    }
}
