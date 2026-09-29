<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Skill;
use App\Models\Theme;
use Closure;
use finfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /** Types MIME réellement détectés acceptés pour une image. */
    protected const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

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
        $extensions = implode(',', ProjectFile::allowedExtensions());

        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'published_on'  => ['required', 'date'],
            'description'   => ['required', 'string', 'max:20000'],
            'links'         => ['nullable', 'array', 'max:20'],
            'links.*.label' => ['nullable', 'string', 'max:100'],
            'links.*.url'   => ['nullable', 'url:http,https', 'max:2048'],
            'skills'        => ['nullable', 'array'],
            'skills.*'      => ['integer', 'exists:skills,id'],
            'themes'        => ['nullable', 'array'],
            'themes.*'      => ['integer', 'exists:themes,id'],
            'files'         => ['nullable', 'array', 'max:30'],
            'files.*'       => ['file', 'max:20480', 'extensions:'.$extensions, $this->imageContentRule()],
            'thumbnail'     => ['nullable', 'string', 'max:20'],
            'delete_files'  => ['nullable', 'array'],
            'delete_files.*' => ['integer'],
        ]);

        $project->fill([
            'title'        => $data['title'],
            'published_on' => $data['published_on'],
            'description'  => $data['description'],
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

        // Suppression des fichiers cochés
        if (! empty($data['delete_files'])) {
            $project->files()->whereIn('id', $data['delete_files'])->get()->each->delete();
        }

        // Nouveaux fichiers
        $newFiles = [];
        $position = (int) $project->files()->max('position');
        foreach ($request->file('files', []) as $index => $upload) {
            $newFiles[$index] = $this->storeFile($project, $upload, ++$position);
        }

        $project->forceFill(['thumbnail_file_id' => $this->resolveThumbnail($project, $data['thumbnail'] ?? null, $newFiles)])->save();

        return $project;
    }

    protected function storeFile(Project $project, UploadedFile $upload, int $position): ProjectFile
    {
        $mime = $this->detectMime($upload);
        $isImage = in_array(strtolower($upload->getClientOriginalExtension()), ProjectFile::IMAGE_EXTENSIONS, true);

        return $project->files()->create([
            'path'          => $upload->store('projects/'.$project->id, ProjectFile::DISK),
            'original_name' => mb_substr(basename($upload->getClientOriginalName()), 0, 255),
            'mime_type'     => $isImage ? $mime : ($mime ?: 'application/octet-stream'),
            'size'          => $upload->getSize(),
            'is_image'      => $isImage,
            'position'      => $position,
        ]);
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

    /** Type MIME déterminé à partir du contenu réel du fichier. */
    protected function detectMime(UploadedFile $file): string
    {
        return (string) ((new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: 'application/octet-stream');
    }

    /**
     * Un fichier portant une extension d'image doit réellement être une image
     * (le contenu est vérifié, pas seulement l'extension).
     */
    protected function imageContentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $isImageExtension = in_array(strtolower($value->getClientOriginalExtension()), ProjectFile::IMAGE_EXTENSIONS, true);

            if ($isImageExtension && ! in_array($this->detectMime($value), self::IMAGE_MIMES, true)) {
                $fail('Le fichier « '.$value->getClientOriginalName().' » n\'est pas une image valide.');
            }
        };
    }
}
