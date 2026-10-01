<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['label', 'url', 'position'])]
class ProjectLink extends Model
{
    protected static function booted(): void
    {
        // Lien GitHub enregistré : cache vidé, la prochaine visite récupère des données à jour.
        static::saved(fn (ProjectLink $link) => \App\Services\GithubRepositories::forget($link->url));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isGithub(): bool
    {
        $host = strtolower((string) parse_url($this->url, PHP_URL_HOST));

        return $host === 'github.com' || str_ends_with($host, '.github.com');
    }

    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        if ($this->isGithub()) {
            return 'Dépôt GitHub';
        }

        return (string) (parse_url($this->url, PHP_URL_HOST) ?: $this->url);
    }
}
