<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesAttachments;
use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\Page;
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
        $user = $request->user();

        // Rédacteurs (admins, contributeurs) : tous les articles, brouillons compris.
        // Public : article publié et page Veille accessible.
        abort_unless(
            $user?->canWriteArticles()
                || ($article->isPublished() && Page::isKeyAccessibleBy('veille', $user))
                || $this->viaPreviewLink($request, $article),
            404
        );

        return $this->attachmentResponse($request, $file);
    }

    /** Lecteur d'un brouillon partagé : le lien ouvert dans cette session est toujours valide. */
    protected function viaPreviewLink(Request $request, Article $article): bool
    {
        $token = $request->session()->get('article_preview.'.$article->id);

        return $token && Article::findByPreviewToken($token)?->is($article);
    }
}
