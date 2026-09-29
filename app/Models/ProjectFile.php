<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

#[Fillable(['path', 'original_name', 'mime_type', 'size', 'is_image', 'position'])]
class ProjectFile extends Model
{
    /** Disque privé : les fichiers sont servis par un contrôleur qui vérifie la visibilité. */
    public const DISK = 'local';

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public const DOCUMENT_EXTENSIONS = [
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'odg',
    ];

    protected function casts(): array
    {
        return [
            'is_image' => 'boolean',
            'size'     => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (ProjectFile $file) => Storage::disk(self::DISK)->delete($file->path));
    }

    /** @return list<string> */
    public static function allowedExtensions(): array
    {
        return [...self::IMAGE_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS];
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

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    /** Famille de document, utilisée pour l'icône. */
    public function kind(): string
    {
        return match ($this->extension()) {
            'pdf'                 => 'pdf',
            'doc', 'docx', 'odt'  => 'text',
            'xls', 'xlsx', 'ods'  => 'sheet',
            'ppt', 'pptx', 'odp'  => 'slides',
            'odg'                 => 'drawing',
            default               => $this->is_image ? 'image' : 'file',
        };
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
