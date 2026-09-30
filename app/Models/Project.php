<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\EditorContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable(['title', 'published_on', 'description', 'thumbnail_file_id'])]
class Project extends Model
{
    use HasUniqueSlug;

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'description'  => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Les fichiers physiques sont supprimés avec le projet.
        static::deleting(function (Project $project) {
            $project->forceFill(['thumbnail_file_id' => null])->saveQuietly();
            $project->files()->each(fn (ProjectFile $file) => $file->delete());
        });
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProjectLink::class)->orderBy('position')->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class)->orderBy('position')->orderBy('id');
    }

    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class, 'thumbnail_file_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->orderBy('position')->orderBy('name');
    }

    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class)->orderBy('position')->orderBy('name');
    }

    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('published_on')->orderByDesc('id');
    }

    /** @return Collection<int, ProjectFile> */
    public function images(): Collection
    {
        return $this->files->where('is_image', true)->values();
    }

    /** @return Collection<int, ProjectFile> */
    public function documents(): Collection
    {
        return $this->files->where('is_image', false)->values();
    }

    /** Résumé en texte brut de la description (cartes, balises meta). */
    public function excerpt(int $limit = 180): string
    {
        return Str::limit(Str::squish(EditorContent::toText($this->description)), $limit);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail?->url();
    }
}
