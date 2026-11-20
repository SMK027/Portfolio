<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Project;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        return view('public.projects.index', [
            'page'     => $request->attributes->get('page'),
            'themes'   => Theme::ordered()->whereHas('projects', fn ($q) => $q->listed())->withCount(['projects' => fn ($q) => $q->listed()])->get(),
            'projects' => Project::with('thumbnail', 'themes', 'skills')->listed()->latestFirst()->paginate(12),
        ]);
    }

    public function theme(Request $request, Theme $theme): View
    {
        return view('public.projects.theme', [
            'page'     => $request->attributes->get('page'),
            'theme'    => $theme,
            'themes'   => Theme::ordered()->whereHas('projects', fn ($q) => $q->listed())->withCount(['projects' => fn ($q) => $q->listed()])->get(),
            'projects' => $theme->projects()->with('thumbnail', 'themes', 'skills')->listed()->latestFirst()->paginate(12),
        ]);
    }

    public function show(Request $request, Project $project): View|Response
    {
        $user = $request->user();
        abort_if($project->isHiddenFrom($user), 404);

        if ($project->isInMaintenanceFor($user)) {
            return response()->view('public.projects.maintenance', ['page' => $request->attributes->get('page'), 'project' => $project], 503)
                ->header('Retry-After', 3600);
        }
        if ($project->isLockedFor($user)) {
            return response()->view('public.projects.locked', ['page' => $request->attributes->get('page'), 'project' => $project]);
        }

        $project->load('files', 'links', 'skills', 'themes', 'thumbnail');

        return view('public.projects.show', [
            'page'       => $request->attributes->get('page'),
            'project'    => $project,
            'linkSkills' => Page::isKeyAccessibleBy('competences', $request->user()),
        ]);
    }

    /** Déverrouillage d'un projet protégé : 5 essais par minute et par IP. */
    public function unlock(Request $request, Project $project): RedirectResponse
    {
        abort_if($project->status !== Project::PASSWORD || ! $project->password, 404);
        $request->validate(['password' => ['required', 'string', 'max:100']]);

        if ($error = $project->attemptUnlock($request->input('password'), $request->ip())) {
            throw ValidationException::withMessages(['password' => $error]);
        }

        return redirect()->route('projects.show', $project);
    }
}
