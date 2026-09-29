<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'excerpt', 'content', 'thumbnail_path', 'author_id', 'is_pinned', 'published_at'])]
class Article extends Model
{
    use HasUniqueSlug;

    protected function casts(): array
    {
        return [
            'content'      => 'array',
            'is_pinned'    => 'boolean',
            'published_at' => 'datetime',
        ];
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

    public function status(): string
    {
        return match (true) {
            $this->published_at === null => 'Brouillon',
            $this->isPublished()         => 'Publié',
            default                      => 'Programmé',
        };
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null;
    }

    /** Durée de lecture estimée, en minutes. */
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
