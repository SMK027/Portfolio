<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * Fichier joint (projet, article…) stocké sur le disque privé et servi par
 * un contrôleur, afin que la visibilité de la page parente s'applique.
 */
trait IsAttachment
{
    /** Disque privé : les fichiers ne sont pas accessibles directement. */
    public const DISK = 'local';

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public const DOCUMENT_EXTENSIONS = [
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'odg',
    ];

    /** Types MIME réellement détectés acceptés pour une image. */
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** URL d'affichage (inline pour les images et PDF). */
    abstract public function url(): string;

    /** URL de téléchargement forcé. */
    abstract public function downloadUrl(): string;

    protected static function bootIsAttachment(): void
    {
        static::deleted(fn ($file) => Storage::disk(self::DISK)->delete($file->path));
    }

    protected function initializeIsAttachment(): void
    {
        $this->mergeFillable(['path', 'original_name', 'mime_type', 'size', 'is_image', 'position']);
        $this->mergeCasts(['is_image' => 'boolean', 'size' => 'integer']);
    }

    /** @return list<string> */
    public static function allowedExtensions(): array
    {
        return [...self::IMAGE_EXTENSIONS, ...self::DOCUMENT_EXTENSIONS];
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isPdf(): bool
    {
        return $this->extension() === 'pdf' && $this->mime_type === 'application/pdf';
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
