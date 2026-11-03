<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Page;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Skill;
use App\Support\EditorContent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Mode terminal : le portfolio parcouru en ligne de commande (ls, cat, cd…).
 * Les données sont celles des pages publiques, selon leur visibilité.
 */
class TerminalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $canSee = fn (string $key) => Page::isKeyAccessibleBy($key, $user);
        $profile = Profile::current();

        $sections = array_filter([
            'competences' => $canSee('competences') ? Skill::groupByCategory(Skill::ordered()->get())
                ->map(fn ($skills) => $skills->map(fn (Skill $s) => ['name' => $s->name, 'level' => $s->level])->values())->all() : null,
            'projets' => $canSee('projets') ? Project::latestFirst()->limit(30)->get()->map(fn (Project $p) => [
                'name' => $p->slug, 'title' => $p->title, 'date' => $p->published_on?->format('Y'), 'url' => route('projects.show', $p),
            ])->all() : null,
            'veille' => $canSee('veille') ? Article::published()->forListing()->limit(30)->get()->map(fn (Article $a) => [
                'name' => $a->slug, 'title' => $a->title, 'date' => $a->published_at->format('Y-m-d'), 'excerpt' => $a->excerpt, 'url' => route('articles.show', $a),
            ])->all() : null,
            'experiences' => $canSee('experiences') ? Experience::ordered()->get()->map(fn (Experience $e) => [
                'title' => $e->title, 'where' => collect([$e->company, $e->location])->filter()->join(', '),
                'period' => collect([$e->formatDate($e->start_date, true), $e->end_date ? $e->formatDate($e->end_date, true) : 'aujourd\'hui'])->filter()->join(' → '),
            ])->all() : null,
            'formations' => $canSee('formations') ? Education::ordered()->get()->map(fn (Education $e) => [
                'title' => $e->title, 'where' => collect([$e->institution, $e->location])->filter()->join(', '),
                'period' => collect([$e->formatDate($e->start_date, true), $e->end_date ? $e->formatDate($e->end_date, true) : 'aujourd\'hui'])->filter()->join(' → '),
            ])->all() : null,
            'recommandations' => $canSee('recommandations') ? Recommendation::published()->ordered()->get()->map(fn (Recommendation $r) => [
                'author' => $r->author_name, 'title' => $r->authorTitle(), 'content' => $r->content,
            ])->all() : null,
        ], fn ($value) => $value !== null);

        return view('public.terminal', [
            'data' => [
                'name'     => $profile->fullName(),
                'user'     => Str::slug($profile->first_name ?: 'visiteur', '') ?: 'visiteur',
                'headline' => $profile->headline,
                'location' => $profile->location,
                'about'    => Str::limit(EditorContent::toText($profile->about), 1500),
                'email'    => $canSee('contact') ? $profile->email : null,
                'cv'       => $profile->cvUrl(),
                'socials'  => $profile->socialLinks()->map(fn ($l) => ['name' => $l['name'], 'url' => $l['url']])->all(),
                'sections' => $sections,
                'pages'    => Page::visibleTo($user)->mapWithKeys(fn (Page $p) => [$p->key => ['title' => $p->title, 'url' => $p->url()]])->all(),
                'home'     => route('home'),
            ],
        ]);
    }
}
