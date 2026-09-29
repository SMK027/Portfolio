<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Skill;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function createProject(User $admin, array $overrides = []): Project
    {
        $this->actingAs($admin)->post(route('admin.projets.store'), array_merge([
            'title'        => 'Supervision réseau',
            'published_on' => '2026-05-01',
            'description'  => 'Mise en place d\'une supervision avec Zabbix.',
        ], $overrides))->assertSessionHasNoErrors();

        return Project::latest('id')->first();
    }

    public function test_admin_creates_a_complete_project(): void
    {
        $admin = User::factory()->admin()->create();
        $skill = Skill::create(['name' => 'Zabbix']);
        $theme = Theme::create(['name' => 'Réseau']);

        $project = $this->createProject($admin, [
            'skills'    => [$skill->id],
            'themes'    => [$theme->id],
            'links'     => [
                ['label' => '', 'url' => 'https://github.com/moi/supervision'],
                ['label' => 'Démo', 'url' => 'https://demo.example.com'],
                ['label' => '', 'url' => ''],
            ],
            'files'     => [
                UploadedFile::fake()->create('rapport.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->image('capture.png', 800, 600),
                UploadedFile::fake()->create('budget.xlsx', 50),
                UploadedFile::fake()->create('slides.odp', 50),
            ],
            'thumbnail' => 'new:1',
        ]);

        $this->assertSame('supervision-reseau', $project->slug);
        $this->assertCount(2, $project->links);
        $this->assertTrue($project->links->first()->isGithub());
        $this->assertCount(4, $project->files);
        $this->assertCount(1, $project->images());
        $this->assertSame('capture.png', $project->thumbnail->original_name);
        $this->assertTrue($project->skills->contains($skill));
        $this->assertTrue($project->themes->contains($theme));

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Supervision réseau')
            ->assertSee('Dépôt GitHub')
            ->assertSee('rapport.pdf')
            ->assertSee('aria-roledescription="carrousel"', false);

        $this->get(route('projects.theme', $theme))->assertOk()->assertSee('Supervision réseau');
    }

    public function test_thumbnail_must_be_an_image(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->createProject($admin, [
            'files'     => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
            'thumbnail' => 'new:0',
        ]);

        $this->assertNull($project->thumbnail_file_id);
    }

    public function test_disallowed_and_fake_image_files_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.projets.store'), [
            'title' => 'X', 'published_on' => '2026-01-01', 'description' => 'X',
            'files' => [UploadedFile::fake()->create('script.php', 1)],
        ])->assertSessionHasErrors('files.0');

        $this->actingAs($admin)->post(route('admin.projets.store'), [
            'title' => 'X', 'published_on' => '2026-01-01', 'description' => 'X',
            'files' => [UploadedFile::fake()->createWithContent('photo.png', '<html><script>alert(1)</script></html>')],
        ])->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_files_are_served_and_follow_page_visibility(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->createProject($admin, ['files' => [UploadedFile::fake()->create('rapport.docx', 10)]]);
        $file = $project->files->first();
        auth()->logout();

        $this->get($file->url())->assertOk()->assertHeader('Content-Disposition');

        Page::where('key', 'projets')->update(['is_public' => false]);
        Page::flushCache();
        $this->get($file->url())->assertNotFound();
    }

    public function test_deleting_a_project_removes_its_files(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->createProject($admin, ['files' => [UploadedFile::fake()->image('a.jpg')], 'thumbnail' => 'new:0']);
        $path = $project->files->first()->path;
        Storage::disk(ProjectFile::DISK)->assertExists($path);

        $this->actingAs($admin)->delete(route('admin.projets.destroy', $project))->assertRedirect();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_files', 0);
        Storage::disk(ProjectFile::DISK)->assertMissing($path);
    }

    public function test_admin_can_update_theme_name_and_background(): void
    {
        $admin = User::factory()->admin()->create();
        $theme = Theme::create(['name' => 'Programmation']);

        $this->actingAs($admin)->put(route('admin.themes.update', $theme), [
            'name'       => 'Développement',
            'background' => UploadedFile::fake()->image('fond.jpg', 1600, 900),
        ])->assertSessionHasNoErrors();

        $theme->refresh();
        $this->assertSame('Développement', $theme->name);
        $this->assertSame('developpement', $theme->slug);
        Storage::disk('public')->assertExists($theme->background_path);
    }
}
