<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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

#[Fillable(['title', 'status', 'published_on', 'description', 'description_editor', 'description_markdown', 'thumbnail_file_id'])]
class Project extends Model
{
    use Auditable, HasUniqueSlug;

    public const PUBLIC = 'public';
    public const PASSWORD = 'password';
    public const UNLISTED = 'unlisted';
    public const MAINTENANCE = 'maintenance';
    public const DRAFT = 'draft';
    public const PRIVATE = 'private';

    /** Statuts et libellés. */
    public const STATUSES = [
        self::PUBLIC      => 'Public',
        self::PASSWORD    => 'Protégé par mot de passe',
        self::UNLISTED    => 'Non indexé (accessible par lien direct)',
        self::MAINTENANCE => 'En maintenance',
        self::DRAFT       => 'Brouillon',
        self::PRIVATE     => 'Privé (administrateurs uniquement)',
    ];

    /** Statuts affichés dans les listes publiques (projets, accueil, recherche, plan du site…). */
    public const LISTED = [self::PUBLIC, self::PASSWORD];

    /** Statuts réservés aux administrateurs (404 pour les autres). */
    public const HIDDEN = [self::DRAFT, self::PRIVATE];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'description'  => 'array',
            'password'     => 'hashed',
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

    /** Projets affichés dans les listes publiques. */
    public function scopeListed(Builder $query): void
    {
        $query->whereIn('status', self::LISTED);
    }

    public function isListed(): bool
    {
        return in_array($this->status, self::LISTED, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Badge court (back-office, aperçu administrateur). */
    public function statusBadge(): string
    {
        return [
            self::PUBLIC => 'Public', self::PASSWORD => 'Mot de passe', self::UNLISTED => 'Non indexé',
            self::MAINTENANCE => 'Maintenance', self::DRAFT => 'Brouillon', self::PRIVATE => 'Privé',
        ][$this->status] ?? $this->status;
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
