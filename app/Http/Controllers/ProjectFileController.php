<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert les fichiers d'un projet (stockés hors du dossier public) afin
 * que la visibilité de la page "Projets" s'applique aussi aux fichiers.
 */
class ProjectFileController extends Controller
{
    public function show(Request $request, Project $project, ProjectFile $file): StreamedResponse
    {
        $disk = Storage::disk(ProjectFile::DISK);
        abort_unless($disk->exists($file->path), 404);

        // Images et vrais PDF s'affichent dans le navigateur, le reste est téléchargé.
        $isPdf = $file->extension() === 'pdf' && $file->mime_type === 'application/pdf';
        $inline = ! $request->boolean('download') && ($file->is_image || $isPdf);

        $headers = [
            'Content-Type'            => $file->mime_type,
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
            'Cache-Control'           => 'private, max-age=86400',
        ];

        return $inline
            ? $disk->response($file->path, $file->original_name, $headers)
            : $disk->download($file->path, $file->original_name, $headers);
    }
}
