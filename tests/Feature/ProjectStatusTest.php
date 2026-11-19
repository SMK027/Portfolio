<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    protected function project(string $status, array $extra = []): Project
    {
        $this->actingAs($this->admin)->post(route('admin.projets.store'), [
            'title' => 'Projet '.$status, 'published_on' => '2026-05-01', 'description' => 'Description du projet.', 'status' => $status, ...$extra,
        ])->assertSessionHasNoErrors();
        auth()->logout();

        return Project::latest('id')->first();
    }

    public function test_private_and_draft_projects_are_admin_only(): void
    {
        foreach ([Project::PRIVATE, Project::DRAFT] as $status) {
            $project = $this->project($status, ['files' => [UploadedFile::fake()->create('rapport.docx', 10)]]);
            $this->get(route('projects.show', $project))->assertNotFound();
            $this->get($project->files->first()->url())->assertNotFound();
            $this->actingAs($this->admin)->get(route('projects.show', $project))->assertOk()->assertSee('Aperçu administrateur');
            auth()->logout();
        }
    }

    public function test_maintenance_projects_answer_503_to_visitors(): void
    {
        $project = $this->project(Project::MAINTENANCE);

        $this->get(route('projects.show', $project))->assertStatus(503)->assertSee('maintenance')->assertDontSee('Description du projet.');
        $this->actingAs($this->admin)->get(route('projects.show', $project))->assertOk();
    }

    public function test_unlisted_projects_are_reachable_but_not_indexed(): void
    {
        $project = $this->project(Project::UNLISTED);

        $this->get(route('projects.show', $project))->assertOk()->assertSee('Description du projet.')->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_password_projects_are_unlocked_per_session(): void
    {
        $this->actingAs($this->admin)->post(route('admin.projets.store'), [
            'title' => 'Sans mot de passe', 'published_on' => '2026-05-01', 'description' => 'x', 'status' => Project::PASSWORD,
        ])->assertSessionHasErrors('password');

        $project = $this->project(Project::PASSWORD, ['password' => 'secret-42', 'files' => [UploadedFile::fake()->create('rapport.docx', 10)]]);
        $file = $project->files->first();

        $this->get(route('projects.show', $project))->assertOk()->assertSee('Mot de passe')->assertDontSee('Description du projet.');
        $this->get($file->url())->assertForbidden();

        $this->post(route('projects.unlock', $project), ['password' => 'mauvais'])->assertSessionHasErrors('password');
        $this->post(route('projects.unlock', $project), ['password' => 'secret-42'])->assertRedirect(route('projects.show', $project));
        $this->get(route('projects.show', $project))->assertSee('Description du projet.');
        $this->get($file->url())->assertOk();

        // Nouveau mot de passe : la session est reverrouillée ; champ vide = mot de passe conservé
        $project->forceFill(['password' => 'nouveau-42'])->save();
        $this->get(route('projects.show', $project))->assertDontSee('Description du projet.');
        $this->actingAs($this->admin)->put(route('admin.projets.update', $project), [
            'title' => $project->title, 'published_on' => '2026-05-01', 'description' => 'Description du projet.', 'status' => Project::PASSWORD,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nouveau-42', $project->fresh()->password));

        // Retour au statut public : mot de passe effacé
        $this->put(route('admin.projets.update', $project), [
            'title' => $project->title, 'published_on' => '2026-05-01', 'description' => 'Description du projet.', 'status' => Project::PUBLIC,
        ]);
        $this->assertNull($project->fresh()->password);
    }

    public function test_unlock_attempts_are_rate_limited(): void
    {
        $project = $this->project(Project::PASSWORD, ['password' => 'secret-42']);
        foreach (range(1, 5) as $i) {
            $this->post(route('projects.unlock', $project), ['password' => 'non']);
        }
        $this->post(route('projects.unlock', $project), ['password' => 'secret-42'])->assertSessionHasErrors('password');
    }
}
