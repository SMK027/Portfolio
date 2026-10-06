<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

trait HandlesUploads
{
    /** Règles de validation d'une image publique (SVG exclu : risque XSS). */
    protected function imageRules(int $maxKb = 5120): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.$maxKb];
    }

    /**
     * Remplace (ou supprime) un fichier stocké sur le disque public.
     * Les images sont redimensionnées et converties en WebP (App\Services\ImageOptimizer).
     * Retourne le nouveau chemin, l'ancien s'il est conservé, ou null.
     */
    protected function syncPublicFile(Request $request, string $field, ?string $currentPath, string $directory): ?string
    {
        if ($request->hasFile($field)) {
            $this->deletePublicFile($currentPath);

            return app(ImageOptimizer::class)->store($request->file($field), $directory)['path'];
        }

        if ($request->boolean('remove_'.$field)) {
            $this->deletePublicFile($currentPath);

            return null;
        }

        return $currentPath;
    }

    protected function deletePublicFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Décode et valide sommairement le JSON produit par Editor.js.
     *
     * @return array<string, mixed>|null
     */
    protected function editorContent(Request $request, string $field): ?array
    {
        $raw = $request->input($field);

        if (blank($raw)) {
            return null;
        }

        $data = json_decode((string) $raw, true);

        if (! is_array($data) || ! is_array($data['blocks'] ?? null)) {
            throw ValidationException::withMessages([$field => 'Le contenu de l\'éditeur est invalide.']);
        }

        return [
            'time'    => $data['time'] ?? null,
            'blocks'  => array_values($data['blocks']),
            'version' => $data['version'] ?? null,
        ];
    }
}
