<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\EditorContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function editorJson(array $blocks): string
    {
        return json_encode(['time' => 1, 'version' => '2.31', 'blocks' => $blocks]);
    }

    public function test_description_is_saved_as_rich_text_and_rendered(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.projets.store'), [
            'title'        => 'Portfolio',
            'published_on' => '2026-09-01',
            'description'  => $this->editorJson([
                ['type' => 'header', 'data' => ['text' => 'Contexte', 'level' => 2]],
                ['type' => 'paragraph', 'data' => ['text' => 'Un site en <b>Laravel</b> <script>alert(1)</script>']],
                ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => [['content' => 'Docker', 'meta' => [], 'items' => []]]]],
            ]),
        ])->assertSessionHasNoErrors();

        $project = Project::sole();
        $this->assertSame(['header', 'paragraph', 'list'], array_column($project->description['blocks'], 'type'));

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('<h2>Contexte</h2>', false)
            ->assertSee('<b>Laravel</b>', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        // Résumé en texte brut (cartes, balise meta)
        $this->get(route('projects.index'))->assertSee('Contexte Un site en Laravel');
        $this->assertStringNotContainsString('<b>', $project->excerpt());
    }

    public function test_plain_text_is_still_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.projets.store'), [
            'title' => 'Texte', 'published_on' => '2026-01-01', 'description' => "Ligne 1\nLigne 2\n\nParagraphe <2>",
        ])->assertSessionHasNoErrors();

        $blocks = Project::sole()->description['blocks'];
        $this->assertCount(2, $blocks);
        $this->assertSame('Ligne 1<br>'."\n".'Ligne 2', $blocks[0]['data']['text']);
        $this->assertSame('Paragraphe &lt;2&gt;', $blocks[1]['data']['text']);
    }

    public function test_empty_description_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.projets.store'), [
            'title' => 'Vide', 'published_on' => '2026-01-01',
            'description' => $this->editorJson([['type' => 'paragraph', 'data' => ['text' => '   ']]]),
        ])->assertSessionHasErrors('description');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_form_uses_the_editor(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.projets.create'))
            ->assertOk()
            ->assertSee('data-editorjs', false)
            ->assertSee('name="description"', false);
    }

    public function test_migration_converts_existing_text_descriptions(): void
    {
        DB::table('projects')->insert([
            'title' => 'Ancien', 'slug' => 'ancien', 'published_on' => '2024-01-01',
            'description' => "Premier paragraphe\navec retour\n\nSecond <b>paragraphe</b>",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_30_000005_convert_project_descriptions_to_editorjs.php');
        $migration->up();
        $migration->up(); // idempotente

        $description = Project::sole()->description;
        $this->assertSame(['paragraph', 'paragraph'], array_column($description['blocks'], 'type'));
        $this->assertSame('Second &lt;b&gt;paragraphe&lt;/b&gt;', $description['blocks'][1]['data']['text']);

        $migration->down();
        $this->assertSame("Premier paragraphe avec retour\n\nSecond <b>paragraphe</b>", DB::table('projects')->value('description'));
    }

    public function test_editor_content_helpers(): void
    {
        $this->assertTrue(EditorContent::isEmpty(null));
        $this->assertFalse(EditorContent::isEmpty(['blocks' => [['type' => 'image', 'data' => ['file' => ['url' => '/x.png']]]]]));
        $this->assertSame('A B', EditorContent::toText(['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'A<br>B']]]]));
    }
}
