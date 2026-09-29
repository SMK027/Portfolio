<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Models\Diploma;
use App\Models\Education;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pages du parcours : formations, diplômes, certifications, compétences.
 */
class BackgroundController extends Controller
{
    public function formations(Request $request): View
    {
        return view('public.formations', [
            'page'       => $request->attributes->get('page'),
            'educations' => Education::ordered()->get(),
        ]);
    }

    public function diplomas(Request $request): View
    {
        return view('public.diplomas', [
            'page'     => $request->attributes->get('page'),
            'diplomas' => Diploma::ordered()->get(),
        ]);
    }

    public function certifications(Request $request): View
    {
        return view('public.certifications', [
            'page'           => $request->attributes->get('page'),
            'certifications' => Certification::ordered()->get(),
        ]);
    }

    public function skills(Request $request): View
    {
        return view('public.skills', [
            'page'   => $request->attributes->get('page'),
            'groups' => Skill::ordered()->withCount('projects')->get()
                ->groupBy(fn (Skill $skill) => $skill->category ?: 'Autres'),
        ]);
    }
}
