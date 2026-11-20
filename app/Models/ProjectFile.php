<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    use Auditable, IsAttachment;

    /** Fichiers web et Markdown, propres aux projets (toujours servis en téléchargement). */
    public const CODE_EXTENSIONS = ['html', 'htm', 'md', 'css', 'js'];

    /** @return list<string> */
    public static function allowedExtensions(): array
    {
        return [...self::IMAGE_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS, ...self::CODE_EXTENSIONS];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function url(): string
    {
        return route('projects.files.show', [$this->project_id, $this]);
    }

    public function downloadUrl(): string
    {
        return route('projects.files.show', [$this->project_id, $this, 'download' => 1]);
    }
}
