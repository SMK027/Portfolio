<?php

namespace App\Http\Controllers;

use App\Services\SiteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, SiteSearch $search): View|JsonResponse
    {
        $query = mb_substr((string) $request->query('q', ''), 0, 100);
        $results = $search->search($query, $request->user(), $request->wantsJson() ? 8 : 50);

        $suggestion = $search->suggestion($query);

        if ($request->wantsJson()) {
            $terms = $search->terms($query);

            // Versions surlignées (HTML échappé côté serveur) pour la palette de recherche.
            return response()->json(['query' => $query, 'suggestion' => $suggestion, 'results' => $results->map(fn ($r) => $r + [
                'title_html'   => (string) SiteSearch::highlight($r['title'], $terms),
                'excerpt_html' => (string) SiteSearch::highlight($r['excerpt'], $terms),
            ])]);
        }

        return view('public.search', ['query' => $query, 'results' => $results, 'suggestion' => $suggestion, 'terms' => $search->terms($query)]);
    }
}
