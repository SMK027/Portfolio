<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $theme = $request->filled('theme')
            ? Theme::where('slug', $request->string('theme'))->first()
            : null;

        $base = Article::with('author', 'coauthors', 'themes')
            ->published()
            ->when($theme, fn ($q) => $q->whereHas('themes', fn ($t) => $t->whereKey($theme->id)));

        return view('public.articles.index', [
            'page'     => $request->attributes->get('page'),
            'theme'    => $theme,
            'themes'   => Theme::ordered()->whereHas('articles', fn ($q) => $q->published())->get(),
            // Les articles épinglés restent en tête, au-dessus du fil chronologique.
            'pinned'   => (clone $base)->where('is_pinned', true)->orderByDesc('published_at')->get(),
            'articles' => (clone $base)->where('is_pinned', false)->forListing()->paginate(9)->withQueryString(),
        ]);
    }

    /** Brouillon partagé par lien secret : lisible sans compte jusqu'à expiration (si la page Veille est publique). */
    public function preview(Request $request, string $token): View
    {
        $article = Article::findByPreviewToken($token) ?? abort(404);
        // Page Veille privée : aucun article n'est lisible hors administration, liens de relecture compris.
        abort_unless(\App\Models\Page::isKeyAccessibleBy('veille', $request->user()), 404);
        // Autorise aussi ses pièces jointes pour cette session (voir ArticleFileController).
        $request->session()->put('article_preview.'.$article->id, $token);
        $article->load('author', 'coauthors', 'themes', 'files');

        return view('public.articles.show', [
            'page'    => \App\Models\Page::findByKey('veille'),
            'article' => $article,
            'related' => collect(),
            'preview' => true,
        ]);
    }

    public function show(Request $request, Article $article): View
    {
        // Brouillons et articles programmés : aperçu réservé aux administrateurs.
        abort_unless($article->isPublished() || $request->user()?->isAdmin(), 404);

        $article->load('author', 'coauthors', 'themes', 'files');

        return view('public.articles.show', [
            'page'    => $request->attributes->get('page'),
            'article' => $article,
            'related' => Article::published()
                ->whereKeyNot($article->id)
                ->whereHas('themes', fn ($q) => $q->whereIn('themes.id', $article->themes->pluck('id')))
                ->forListing()
                ->limit(3)
                ->get(),
        ]);
    }
}
