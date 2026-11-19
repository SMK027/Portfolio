<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesRichText;
use App\Http\Controllers\Admin\Concerns\StoresAttachments;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Skill;
use App\Models\Theme;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use HandlesRichText, StoresAttachments;

    public function index(Request $request): View
    {
        return view('admin.projects.index', [
            'projects' => Project::with('thumbnail', 'themes')
                ->withCount('files', 'skills')
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
                ->when(array_key_exists($request->string('statut')->toString(), Project::STATUSES), fn ($q) => $q->where('status', $request->string('statut')))
                ->latestFirst()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.projects.form', $this->formData(new Project(['published_on' => now(), 'status' => Project::DRAFT])));
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
            'status'        => ['nullable', 'in:'.implode(',', array_keys(Project::STATUSES))],
            // Obligatoire à l'activation de la protection, facultatif ensuite (vide = inchangé)
            'password'      => [$request->input('status') === Project::PASSWORD && ! $project->password ? 'required' : 'nullable', 'string', 'min:4', 'max:100'],
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

        $relationsBefore = $this->relations($project);
        $data['status'] ??= $project->status ?? Project::PUBLIC;

        $project->fill([
            'title'        => $data['title'],
            'published_on' => $data['published_on'],
            'status'       => $data['status'],
            ...$this->richText($request, 'description', true, 'description'),
        ]);
        if ($data['status'] !== Project::PASSWORD) {
            $project->password = null;
        } elseif (filled($data['password'] ?? null)) {
            $project->password = $data['password'];
        }
        $project->save();

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

        app(AuditTrail::class)->recordRelations($project, $relationsBefore, $this->relations($project->fresh()));

        $project->forceFill(['thumbnail_file_id' => $this->resolveThumbnail($project, $data['thumbnail'] ?? null, $newFiles)])->save();

        return $project;
    }

    /** @return array<string, list<string>> */
    protected function relations(Project $project): array
    {
        if (! $project->exists) {
            return ['thèmes' => [], 'compétences' => [], 'liens' => []];
        }

        return [
            'thèmes'      => $project->themes()->pluck('name')->all(),
            'compétences' => $project->skills()->pluck('name')->all(),
            'liens'       => $project->links()->pluck('url')->all(),
        ];
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
