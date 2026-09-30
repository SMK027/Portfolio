<?php

namespace App\Http\Resources;

use App\Services\Markdown\EditorJsToMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailed = $request->route('project') !== null;

        return [
            'id'           => $this->id,
            'slug'         => $this->slug,
            'title'        => $this->title,
            'published_on' => $this->published_on?->toDateString(),
            'excerpt'      => $this->excerpt(),
            'themes'       => $this->whenLoaded('themes', fn () => $this->themes->pluck('name')),
            'skills'       => $this->whenLoaded('skills', fn () => $this->skills->pluck('name')),
            'links'        => $this->whenLoaded('links', fn () => $this->links->map(fn ($l) => ['label' => $l->label, 'url' => $l->url])),
            'files'        => $this->whenLoaded('files', fn () => $this->files->map(fn ($f) => [
                'name' => $f->original_name, 'size' => $f->size, 'is_image' => $f->is_image, 'url' => $f->url(),
            ])),
            'url'          => route('projects.show', $this->resource),
            'description'          => $this->when($detailed, $this->description),
            'description_markdown' => $this->when($detailed, fn () => $this->description_editor === 'markdown' && $this->description_markdown !== null
                ? $this->description_markdown
                : app(EditorJsToMarkdown::class)->convert($this->description)),
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
