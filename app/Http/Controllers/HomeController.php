<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Page;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Les aperçus ne sont affichés que si la page correspondante est accessible.
        $canSee = fn (string $key) => Page::isKeyAccessibleBy($key, $user);

        return view('public.home', [
            'page'     => $request->attributes->get('page'),
            'profile'  => Profile::current(),
            'projects' => $canSee('projets')
                ? Project::with('thumbnail', 'themes')->latestFirst()->limit(3)->get()
                : collect(),
            'articles' => $canSee('veille')
                ? Article::with('author', 'themes')->published()->forListing()->limit(3)->get()
                : collect(),
            'skills'   => $canSee('competences')
                ? Skill::ordered()->get()->groupBy(fn (Skill $skill) => $skill->category ?: 'Autres')
                : collect(),
        ]);
    }
}
