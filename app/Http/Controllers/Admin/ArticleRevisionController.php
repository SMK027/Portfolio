<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Services\AuditTrail;
use App\Support\LineDiff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Historique d'un article : liste, comparaison et restauration des versions. */
class ArticleRevisionController extends Controller
{
    public function index(Article $article): View
    {
        Gate::authorize('view', $article);

        return view('admin.articles.revisions.index', [
            'article'   => $article,
            'revisions' => $article->revisions()->with('user')->get(),
        ]);
    }

    public function show(Request $request, Article $article, ArticleRevision $revision): View
    {
        Gate::authorize('view', $article);
        abort_unless($revision->article_id === $article->id, 404);

        // Par défaut : changements apportés par cette version (par rapport à la précédente).
        $againstCurrent = $request->query('avec') === 'actuelle';
        $previous = $article->revisions()->where('id', '<', $revision->id)->first();

        [$old, $new, $label] = $againstCurrent
            ? [$revision->comparableLines(), ArticleRevision::linesFor($article->title, $article->excerpt, $article->content), 'Cette version → version actuelle']
            : [$previous?->comparableLines() ?? [], $revision->comparableLines(), $previous ? 'Version précédente → cette version' : 'Première version'];

        return view('admin.articles.revisions.show', [
            'article'        => $article,
            'revision'       => $revision,
            'diff'           => LineDiff::compare($old, $new),
            'label'          => $label,
            'againstCurrent' => $againstCurrent,
            'isLatest'       => $article->revisions()->value('id') === $revision->id,
        ]);
    }

    public function restore(Article $article, ArticleRevision $revision, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('update', $article);
        abort_unless($revision->article_id === $article->id, 404);

        $article->pendingRevisionNote = 'Restauration de la version du '.$revision->created_at->format('d/m/Y à H:i');
        $article->fill([
            'title'            => $revision->title,
            'excerpt'          => $revision->excerpt,
            'content'          => $revision->content,
            'content_editor'   => 'blocks',
            'content_markdown' => null,
        ])->save();
        $audit->record('article.revision_restored', $article, meta: ['version' => $revision->created_at->format('d/m/Y H:i')]);

        return redirect()->route('admin.articles.revisions.index', $article)->with('success', 'Version du '.$revision->created_at->format('d/m/Y à H:i').' restaurée.');
    }
}
