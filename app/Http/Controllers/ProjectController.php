<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Project;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        return view('public.projects.index', [
            'page'     => $request->attributes->get('page'),
            'themes'   => Theme::ordered()->has('projects')->withCount('projects')->get(),
            'projects' => Project::with('thumbnail', 'themes', 'skills')->latestFirst()->paginate(12),
        ]);
    }

    public function theme(Request $request, Theme $theme): View
    {
        return view('public.projects.theme', [
            'page'     => $request->attributes->get('page'),
            'theme'    => $theme,
            'themes'   => Theme::ordered()->has('projects')->withCount('projects')->get(),
            'projects' => $theme->projects()->with('thumbnail', 'themes', 'skills')->latestFirst()->paginate(12),
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        $project->load('files', 'links', 'skills', 'themes', 'thumbnail');

        return view('public.projects.show', [
            'page'       => $request->attributes->get('page'),
            'project'    => $project,
            'linkSkills' => Page::isKeyAccessibleBy('competences', $request->user()),
        ]);
    }
}
