<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesAttachments;
use App\Models\Article;
use App\Models\ArticleFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert les pièces jointes d'un article : la visibilité de la page "Veille"
 * et le statut de l'article (brouillon, programmé) s'appliquent aux fichiers.
 */
class ArticleFileController extends Controller
{
    use ServesAttachments;

    public function show(Request $request, Article $article, ArticleFile $file): StreamedResponse
    {
        abort_unless($article->isPublished() || $request->user()?->isAdmin(), 404);

        return $this->attachmentResponse($request, $file);
    }
}
