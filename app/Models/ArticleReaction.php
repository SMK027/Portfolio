<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Réaction anonyme d'un visiteur à un article (non journalisée : ce n'est pas
 * une opération d'administration).
 */
#[Fillable(['article_id', 'emoji', 'visitor'])]
class ArticleReaction extends Model
{
    public const UPDATED_AT = null;

    /** Réactions proposées : clé stockée => [emoji, libellé]. */
    public const EMOJIS = [
        'like'   => ['👍', 'J\'aime'],
        'love'   => ['❤️', 'J\'adore'],
        'fire'   => ['🔥', 'Impressionnant'],
        'idea'   => ['💡', 'Instructif'],
        'party'  => ['🎉', 'Bravo'],
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Empreinte du visiteur : IP et navigateur hachés avec la clé de l'application,
     * sans cookie ni donnée lisible conservée.
     */
    public static function visitorFor(Request $request): string
    {
        return hash_hmac('sha256', $request->ip().'|'.$request->userAgent(), (string) config('app.key'));
    }

    /**
     * Compteurs et réactions du visiteur pour un article.
     *
     * @return array{counts: array<string, int>, mine: list<string>}
     */
    public static function summaryFor(Article $article, string $visitor): array
    {
        $rows = static::where('article_id', $article->id)->get(['emoji', 'visitor']);

        return [
            'counts' => collect(self::EMOJIS)->map(fn ($e, $key) => $rows->where('emoji', $key)->count())->all(),
            'mine'   => $rows->where('visitor', $visitor)->pluck('emoji')->values()->all(),
        ];
    }
}
