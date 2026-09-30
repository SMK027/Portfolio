<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExperienceDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Page::where('key', 'experiences')->update(['is_public' => true]);
    }

    protected function store(array $fields)
    {
        return $this->actingAs($this->admin)->post(route('admin.experiences.store'), array_merge([
            'title' => 'Développeur', 'company' => 'TechSolutions', 'date_precision' => 'year', 'start_date' => '2025',
        ], $fields));
    }

    public function test_missions_with_the_visual_editor(): void
    {
        $this->store(['description' => json_encode(['blocks' => [
            ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => [
                ['content' => 'Refonte du <b>site</b>', 'meta' => [], 'items' => []],
                ['content' => 'Supervision réseau', 'meta' => [], 'items' => []],
            ]]],
        ]])])->assertSessionHasNoErrors();

        $this->assertSame('blocks', Experience::sole()->description_editor);
        auth()->logout();
        $this->get('/experiences')->assertSee('<ul><li>Refonte du <b>site</b></li><li>Supervision réseau</li></ul>', false);
    }

    public function test_missions_in_markdown(): void
    {
        $markdown = "- Développement **Laravel**\n- Déploiement *Docker*";
        $this->store(['description_editor' => 'markdown', 'description_markdown' => $markdown])->assertSessionHasNoErrors();

        $experience = Experience::sole();
        $this->assertSame('markdown', $experience->description_editor);
        $this->assertSame($markdown, $experience->description_markdown);

        $this->actingAs($this->admin)->get(route('admin.experiences.edit', $experience))
            ->assertSee('name="description_markdown"', false)
            ->assertSee('Développement **Laravel**', false);

        auth()->logout();
        $this->get('/experiences')->assertSee('<li>Développement <b>Laravel</b></li>', false);
    }

    public function test_missions_are_optional(): void
    {
        $this->store(['description' => ''])->assertSessionHasNoErrors();

        $this->assertFalse(Experience::sole()->hasDescription());
        auth()->logout();
        $this->get('/experiences')->assertOk()->assertDontSee('editor-content prose-sm', false);
    }

    public function test_migration_converts_existing_text(): void
    {
        $migration = require database_path('migrations/2026_09_30_000008_convert_experience_descriptions_to_rich_text.php');
        $migration->down();

        DB::table('experiences')->insert([
            'title' => 'Stage', 'company' => 'Entreprise', 'date_precision' => 'year', 'start_date' => '2024-01-01',
            'description' => "Mission 1\nMission 2\n\nBilan", 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $experience = Experience::sole();
        $this->assertSame(['paragraph', 'paragraph'], array_column($experience->description['blocks'], 'type'));
        $this->assertSame('blocks', $experience->description_editor);
    }

    public function test_import_accepts_markdown_missions(): void
    {
        Storage::fake('local');
        $payload = ['sections' => ['experiences' => [
            ['title' => 'Alternant', 'company' => 'X', 'start_date' => '2025-09', 'description_markdown' => '- Mission **A**'],
            ['title' => 'Stagiaire', 'company' => 'Y', 'start_date' => '2024', 'description' => "Texte simple\nsur deux lignes"],
        ]]];

        $this->actingAs($this->admin)->post(route('admin.transfer.preview'), [
            'file' => UploadedFile::fake()->createWithContent('i.json', json_encode($payload)), 'sections' => ['experiences'],
        ]);
        $this->actingAs($this->admin)->post(route('admin.transfer.import'));

        $this->assertSame('markdown', Experience::where('title', 'Alternant')->sole()->description_editor);
        $this->assertSame('list', Experience::where('title', 'Alternant')->sole()->description['blocks'][0]['type']);
        $this->assertSame('paragraph', Experience::where('title', 'Stagiaire')->sole()->description['blocks'][0]['type']);
    }
}
