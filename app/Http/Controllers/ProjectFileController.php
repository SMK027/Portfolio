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
        return $this->attachmentResponse($request, $file);
    }
}
