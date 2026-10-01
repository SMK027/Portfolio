<?php

namespace App\Models;

use App\Services\Markdown\EditorJsToMarkdown;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Version enregistrée d'un article (après chaque modification du texte). */
class ArticleRevision extends Model
{
    public const UPDATED_AT = null;

    /** Versions conservées par article (les plus anciennes sont supprimées). */
    public const KEEP = 50;

    protected $fillable = ['user_id', 'user_name', 'title', 'excerpt', 'content', 'note'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Texte comparable ligne à ligne (titre, résumé puis contenu en Markdown). */
    public function comparableLines(): array
    {
        return self::linesFor($this->title, $this->excerpt, $this->content);
    }

    public static function linesFor(?string $title, ?string $excerpt, ?array $content): array
    {
        $markdown = app(EditorJsToMarkdown::class)->convert($content);

        return array_values(array_filter(
            ['# '.$title, ...($excerpt ? ['> '.$excerpt] : []), '', ...preg_split('/\R/u', $markdown)],
            fn ($line, $i) => $i < 3 || trim($line) !== '',
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
