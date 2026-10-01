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

        if ($request->wantsJson()) {
            return response()->json(['query' => $query, 'results' => $results]);
        }

        return view('public.search', ['query' => $query, 'results' => $results]);
    }
}
