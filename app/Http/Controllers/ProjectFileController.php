<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesAttachments;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert les fichiers d'un projet (stockés hors du dossier public) afin
 * que la visibilité de la page "Projets" s'applique aussi aux fichiers.
 */
class ProjectFileController extends Controller
{
    use ServesAttachments;

    public function show(Request $request, Project $project, ProjectFile $file): StreamedResponse
    {
        $user = $request->user();
        abort_if($project->isHiddenFrom($user) || $project->isInMaintenanceFor($user), 404);
        // La miniature reste visible sur la carte d'un projet verrouillé
        abort_if($project->isLockedFor($user) && $file->id !== $project->thumbnail_file_id, 403);

        return $this->attachmentResponse($request, $file);
    }
}
