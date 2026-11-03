<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ajout ou retrait d'une réaction anonyme à un article publié. */
class ArticleReactionController extends Controller
{
    public function __invoke(Request $request, Article $article): JsonResponse
    {
        abort_unless($article->isPublished(), 404);

        $data = $request->validate([
            'emoji'   => ['required', Rule::in(array_keys(ArticleReaction::EMOJIS))],
            'website' => ['prohibited'], // champ piège anti-robot
        ]);

        $visitor = ArticleReaction::visitorFor($request);
        $attributes = ['article_id' => $article->id, 'emoji' => $data['emoji'], 'visitor' => $visitor];

        if (! ArticleReaction::where($attributes)->delete()) {
            ArticleReaction::firstOrCreate($attributes);
        }

        return response()->json(ArticleReaction::summaryFor($article, $visitor));
    }
}
