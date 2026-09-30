<?php

namespace App\Http\Resources;

use App\Services\Markdown\EditorJsToMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Article */
class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailed = $request->route('article') !== null;

        return [
            'id'           => $this->id,
            'slug'         => $this->slug,
            'title'        => $this->title,
            'excerpt'      => $this->excerpt,
            'status'       => match (true) {
                $this->isPublished()                    => 'published',
                $this->published_at !== null            => 'scheduled',
                $this->review_status === 'pending'      => 'pending_review',
                $this->review_status !== null           => 'changes_requested',
                default                                 => 'draft',
            },
            'published_at' => $this->published_at?->toIso8601String(),
            'is_pinned'    => $this->is_pinned,
            'author'       => $this->whenLoaded('author', fn () => $this->author ? ['name' => $this->author->name, 'email' => $this->author->email] : null),
            'coauthors'    => $this->whenLoaded('coauthors', fn () => $this->coauthors->map(fn ($u) => ['name' => $u->name, 'email' => $u->email])),
            'themes'       => $this->whenLoaded('themes', fn () => $this->themes->pluck('name')),
            'review_note'  => $this->review_note,
            'url'          => $this->isPublished() ? route('articles.show', $this->resource) : null,
            'content'          => $this->when($detailed, $this->content),
            'content_markdown' => $this->when($detailed, fn () => $this->content_editor === 'markdown' && $this->content_markdown !== null
                ? $this->content_markdown
                : app(EditorJsToMarkdown::class)->convert($this->content)),
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
