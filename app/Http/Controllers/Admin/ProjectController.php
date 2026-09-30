<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StoresAttachments;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Skill;
use App\Models\Theme;
use App\Support\EditorContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use StoresAttachments;

    public function index(Request $request): View
    {
        return view('admin.projects.index', [
            'projects' => Project::with('thumbnail', 'themes')
                ->withCount('files', 'skills')
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
                ->latestFirst()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.projects.form', $this->formData(new Project(['published_on' => now()])));
    }

    public function store(Request $request): RedirectResponse
    {
        $project = DB::transaction(fn () => $this->save($request, new Project));

        return redirect()->route('admin.projets.edit', $project)->with('success', 'Projet créé.');
    }

    public function edit(Project $project): View
    {
        $project->load('files', 'links', 'skills', 'themes');

        return view('admin.projects.form', $this->formData($project));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        DB::transaction(fn () => $this->save($request, $project));

        return redirect()->route('admin.projets.edit', $project->fresh())->with('success', 'Projet mis à jour.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('admin.projets.index')->with('success', 'Projet supprimé.');
    }

    public function destroyFile(Project $project, ProjectFile $file): RedirectResponse
    {
        $file->delete();

        return back()->with('success', 'Fichier supprimé.');
    }

    /** @return array<string, mixed> */
    protected function formData(Project $project): array
    {
        return [
            'project' => $project,
            'skills'  => Skill::ordered()->get(),
            'themes'  => Theme::ordered()->get(),
        ];
    }

    protected function save(Request $request, Project $project): Project
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'published_on'  => ['required', 'date'],
            'description'   => ['required', 'string', 'max:2000000'],
            'links'         => ['nullable', 'array', 'max:20'],
            'links.*.label' => ['nullable', 'string', 'max:100'],
            'links.*.url'   => ['nullable', 'url:http,https', 'max:2048'],
            'skills'        => ['nullable', 'array'],
            'skills.*'      => ['integer', 'exists:skills,id'],
            'themes'        => ['nullable', 'array'],
            'themes.*'      => ['integer', 'exists:themes,id'],
            'thumbnail'     => ['nullable', 'string', 'max:20'],
            ...$this->attachmentRules(ProjectFile::class),
        ]);

        // Contenu Editor.js (le texte brut reste accepté, converti en paragraphes).
        $description = EditorContent::fromInput($data['description']);
        if (EditorContent::isEmpty($description)) {
            throw ValidationException::withMessages(['description' => 'La description est obligatoire.']);
        }

        $project->fill([
            'title'        => $data['title'],
            'published_on' => $data['published_on'],
            'description'  => $description,
        ])->save();

        $project->skills()->sync($data['skills'] ?? []);
        $project->themes()->sync($data['themes'] ?? []);

        // Liens : la liste envoyée remplace l'existante (les lignes vides sont ignorées).
        $project->links()->delete();
        collect($data['links'] ?? [])
            ->filter(fn ($link) => filled($link['url'] ?? null))
            ->values()
            ->each(fn ($link, $i) => $project->links()->create([
                'label'    => $link['label'] ?? null,
                'url'      => $link['url'],
                'position' => $i,
            ]));

        $newFiles = $this->syncAttachments($request, $project->files(), ProjectFile::class, 'projects/'.$project->id);

        $project->forceFill(['thumbnail_file_id' => $this->resolveThumbnail($project, $data['thumbnail'] ?? null, $newFiles)])->save();

        return $project;
    }

    /**
     * Valeur du champ miniature : "file:{id}" (fichier existant), "new:{index}"
     * (fichier téléversé dans la même requête) ou vide. Seule une image est acceptée.
     *
     * @param  array<int, ProjectFile>  $newFiles
     */
    protected function resolveThumbnail(Project $project, ?string $choice, array $newFiles): ?int
    {
        $file = match (true) {
            str_starts_with((string) $choice, 'file:') => $project->files()->find((int) substr($choice, 5)),
            str_starts_with((string) $choice, 'new:')  => $newFiles[(int) substr($choice, 4)] ?? null,
            default                                    => null,
        };

        return $file?->is_image ? $file->id : null;
    }
}
