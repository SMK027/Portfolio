<?php

namespace App\Mail;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Étapes de validation d'un article : soumis (aux admins), publié ou renvoyé (à l'auteur).
 */
class ArticleReviewNotification extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const CHANGES_REQUESTED = 'changes_requested';

    public function __construct(public Article $article, public string $event)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->event) {
            self::SUBMITTED => 'Article à valider : '.$this->article->title,
            self::APPROVED  => 'Votre article est publié : '.$this->article->title,
            default         => 'Votre article est à retravailler : '.$this->article->title,
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.article-review');
    }
}
