<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'title', 'excerpt', 'content', 'content_editor', 'content_markdown', 'thumbnail_path', 'author_id', 'is_pinned', 'published_at',
    'review_status', 'submitted_at', 'review_note', 'reviewed_by', 'reviewed_at',
])]
class Article extends Model
{
    use Auditable, HasUniqueSlug;

    protected function casts(): array
    {
        return [
            'content'      => 'array',
            'is_pinned'    => 'boolean',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at'  => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Les pièces jointes physiques sont supprimées avec l'article.
        static::deleting(fn (Article $article) => $article->files()->each(fn (ArticleFile $file) => $file->delete()));
    }

    public function files(): HasMany
    {
        return $this->hasMany(ArticleFile::class)->orderBy('position')->orderBy('id');
    }

    /** @return Collection<int, ArticleFile> */
    public function images(): Collection
    {
        return $this->files->where('is_image', true)->values();
    }

    /** @return Collection<int, ArticleFile> */
    public function documents(): Collection
    {
        return $this->files->where('is_image', false)->values();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function coauthors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_coauthor')->orderBy('name');
    }

    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class)->orderBy('position')->orderBy('name');
    }

    /** Articles dont la date de publication est atteinte. */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Épinglés d'abord, puis du plus récent au plus ancien. */
    public function scopeForListing(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /** Soumis à validation par un contributeur. */
    public const REVIEW_PENDING = 'pending';

    /** Renvoyé en brouillon par un administrateur, avec un commentaire. */
    public const REVIEW_CHANGES_REQUESTED = 'changes_requested';

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePendingReview(Builder $query): void
    {
        $query->where('review_status', self::REVIEW_PENDING)->whereNull('published_at');
    }

    public function isPendingReview(): bool
    {
        return $this->review_status === self::REVIEW_PENDING && $this->published_at === null;
    }

    /** L'utilisateur est-il l'auteur principal ou un co-auteur ? */
    public function isAuthoredBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->author_id === $user->id
            || ($this->exists && $this->coauthors()->whereKey($user->id)->exists());
    }

    public function status(): string
    {
        return match (true) {
            $this->isPublished()                                        => 'Publié',
            $this->published_at !== null                                => 'Programmé',
            $this->review_status === self::REVIEW_PENDING               => 'En attente de validation',
            $this->review_status === self::REVIEW_CHANGES_REQUESTED     => 'À retravailler',
            default                                                     => 'Brouillon',
        };
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null;
    }

    /** Durée de lecture estimée, en minutes. */
    /**
     * Table des matières (titres de niveau 2 à 4) ; vide s'il y a moins de deux titres.
     *
     * @return list<array{level: int, text: string, id: string}>
     */
    public function tableOfContents(): array
    {
        $headings = app(\App\Services\EditorJsRenderer::class)->headings($this->content);

        return count($headings) >= 2 ? $headings : [];
    }

    /**
     * Table des matières en arborescence : chaque titre contient ses sous-titres
     * (un niveau sauté, h2 → h4, est rattaché au titre précédent le plus proche).
     *
     * @return list<array{level: int, text: string, id: string, children: list<array>}>
     */
    public function tableOfContentsTree(): array
    {
        $root = ['level' => 1, 'children' => []];
        $stack = [&$root];

        foreach ($this->tableOfContents() as $heading) {
            while (count($stack) > 1 && $stack[count($stack) - 1]['level'] >= $heading['level']) {
                array_pop($stack);
            }
            $parent = &$stack[count($stack) - 1];
            $parent['children'][] = $heading + ['children' => []];
            $stack[] = &$parent['children'][count($parent['children']) - 1];
            unset($parent);
        }

        return $root['children'];
    }

    public function readingTime(): int
    {
        $text = collect($this->content['blocks'] ?? [])
            ->flatMap(fn ($block) => Arr::flatten((array) ($block['data'] ?? [])))
            ->filter(fn ($value) => is_string($value))
            ->map(fn ($value) => strip_tags($value))
            ->implode(' ');

        return max(1, (int) ceil(str_word_count($text) / 200));
    }
}
