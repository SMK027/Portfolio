<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesContent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Theme;
use App\Services\AuditTrail;
use App\Support\EditorContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    use ResolvesContent;

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return ProjectResource::collection(Project::with('themes', 'skills', 'links')
            ->when($request->input('q'), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->latestFirst()
            ->paginate((int) $request->input('per_page', 20)));
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load('themes', 'skills', 'links', 'files'));
    }

    public function store(Request $request): JsonResponse
    {
        $project = DB::transaction(fn () => $this->save($request, new Project));

        return (new ProjectResource($project->load('themes', 'skills', 'links', 'files')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Project $project): ProjectResource
    {
        DB::transaction(fn () => $this->save($request, $project));

        return new ProjectResource($project->fresh(['themes', 'skills', 'links', 'files']));
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(null, 204);
    }

    protected function save(Request $request, Project $project): Project
    {
        $creating = ! $project->exists;

        $data = $request->validate([
            'title'                => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'published_on'         => [$creating ? 'required' : 'sometimes', 'date'],
            'description'          => ['nullable', 'array'],
            'description_markdown' => ['nullable', 'string', 'max:1000000'],
            'description_html'     => ['nullable', 'string', 'max:2000000'],
            'themes'               => ['nullable', 'array'],
            'themes.*'             => ['string', 'max:100'],
            'skills'               => ['nullable', 'array'],
            'skills.*'             => ['string', 'max:100'],
            'links'                => ['nullable', 'array', 'max:20'],
            'links.*.label'        => ['nullable', 'string', 'max:100'],
            'links.*.url'          => ['required', 'url:http,https', 'max:2048'],
        ]);

        $description = $this->richTextInput($request, 'description');
        if (($creating || $description !== null) && EditorContent::isEmpty($description['description'] ?? null)) {
            throw ValidationException::withMessages(['description' => 'La description est obligatoire (description, description_markdown ou description_html).']);
        }

        $before = $creating ? ['thèmes' => [], 'compétences' => [], 'liens' => []] : [
            'thèmes' => $project->themes()->pluck('name')->all(), 'compétences' => $project->skills()->pluck('name')->all(), 'liens' => $project->links()->pluck('url')->all(),
        ];

        $project->fill(collect($data)->only(['title', 'published_on'])->all() + ($description ?? []))->save();

        if (array_key_exists('themes', $data)) {
            $project->themes()->sync($this->idsByName(Theme::class, $data['themes'] ?? [], 'themes'));
        }
        if (array_key_exists('skills', $data)) {
            $project->skills()->sync($this->idsByName(Skill::class, $data['skills'] ?? [], 'skills'));
        }
        if (array_key_exists('links', $data)) {
            $project->links()->delete();
            foreach (array_values($data['links'] ?? []) as $i => $link) {
                $project->links()->create(['label' => $link['label'] ?? null, 'url' => $link['url'], 'position' => $i]);
            }
        }

        app(AuditTrail::class)->recordRelations($project, $before, [
            'thèmes' => $project->themes()->pluck('name')->all(), 'compétences' => $project->skills()->pluck('name')->all(), 'liens' => $project->links()->pluck('url')->all(),
        ]);

        return $project;
    }
}
