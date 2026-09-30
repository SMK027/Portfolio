<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Theme;
use App\Models\User;
use App\Services\Transfer\HtmlToEditorJs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create(['email' => 'leo@example.com']);
    }

    protected function jsonFile(array $payload): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.json', json_encode($payload));
    }

    protected function simulate(array $payload, array $sections = null)
    {
        return $this->actingAs($this->admin)->post(route('admin.transfer.preview'), [
            'file'            => $this->jsonFile($payload),
            'sections'        => $sections ?? array_keys(\App\Services\Transfer\ContentTransfer::SECTIONS),
            'download_images' => '0',
        ]);
    }

    protected function confirm()
    {
        return $this->actingAs($this->admin)->post(route('admin.transfer.import'));
    }

    protected function payload(): array
    {
        return ['format' => 'portfolio', 'version' => 1, 'sections' => [
            'profile'     => ['first_name' => 'Léo', 'social_links' => [['name' => 'X', 'url' => 'https://x.com/smk_027']]],
            'educations'  => [['title' => 'BTS SIO', 'institution' => 'Lycée', 'start_date' => 2024, 'end_date' => null]],
            'experiences' => [['title' => 'Développeur', 'company' => 'TechSolutions', 'start_date' => '2025-09']],
            'projects'    => [[
                'title' => 'Montre connectée', 'published_on' => '2025-01-15', 'description' => '<p>Un <b>projet</b></p><ul><li>A</li></ul>',
                'themes' => ['Projets personnels'], 'skills' => ['Arduino'], 'links' => [['url' => 'https://github.com/SMK027/montre']],
            ]],
            'articles'    => [[
                'title' => 'Tendances IA', 'author' => 'leo@example.com', 'coauthors' => ['inconnu@example.com'], 'themes' => ['IA'],
                'published_at' => '2025-03-01T10:00:00+01:00', 'is_pinned' => true,
                'content_html' => '<h1>Intro</h1><p>Texte <b>gras</b><img src="https://example.com/a.png"></p>'
                    .'<ul><li>Un<ol><li>Sous</li></ol></li></ul><iframe src="https://www.youtube.com/embed/abc123"></iframe><!-- commentaire -->',
            ]],
        ]];
    }

    public function test_simulation_saves_nothing_then_confirmation_imports(): void
    {
        $this->simulate($this->payload())->assertRedirect(route('admin.transfer.index'))->assertSessionHas('transfer.report');

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('articles', 0);
        $this->assertDatabaseCount('themes', 0);
        $this->actingAs($this->admin)->get(route('admin.transfer.index'))->assertSee('Simulation')->assertSee('Confirmer');

        $this->confirm()->assertSessionHas('success');

        $this->assertSame('Léo', Profile::current()->first_name);
        $this->assertSame('year', Education::sole()->date_precision);
        $this->assertSame('month', Experience::sole()->date_precision);

        $project = Project::sole();
        $this->assertSame(['Projets personnels'], $project->themes->pluck('name')->all());
        $this->assertSame(['Arduino'], $project->skills->pluck('name')->all());
        $this->assertSame(['paragraph', 'list'], array_column($project->description['blocks'], 'type'));
        $this->assertSame('Un <b>projet</b>', $project->description['blocks'][0]['data']['text']);

        $article = Article::sole();
        $this->assertTrue($article->is_pinned);
        $this->assertTrue($article->author->is($this->admin));
        $this->assertSame(['header', 'paragraph', 'image', 'list', 'embed'], array_column($article->content['blocks'], 'type'));
        $this->assertSame('Sous', $article->content['blocks'][3]['data']['items'][0]['items'][0]['content']);
        $this->assertSame('https://www.youtube.com/embed/abc123', $article->content['blocks'][4]['data']['embed']);

        Storage::disk('local')->assertDirectoryEmpty('imports');
    }

    public function test_reimport_updates_instead_of_duplicating(): void
    {
        $this->simulate($this->payload());
        $this->confirm();

        $payload = $this->payload();
        $payload['sections']['projects'][0]['published_on'] = '2025-02-01';
        $this->simulate($payload);
        $this->confirm();

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('articles', 1);
        $this->assertDatabaseCount('educations', 1);
        $this->assertSame(1, Theme::where('name', 'IA')->count());
        $this->assertSame('2025-02-01', Project::sole()->published_on->toDateString());
    }

    public function test_export_then_import_round_trip(): void
    {
        $this->simulate($this->payload());
        $this->confirm();

        $export = $this->actingAs($this->admin)->post(route('admin.transfer.export'), [
            'sections' => array_keys(\App\Services\Transfer\ContentTransfer::SECTIONS),
        ])->assertOk()->assertHeader('Content-Disposition');

        $json = json_decode($export->getContent(), true);
        $this->assertSame('portfolio', $json['format']);
        $this->assertSame('2024', $json['sections']['educations'][0]['start_date']);

        // Base vidée puis réimport de l'export
        Article::query()->delete();
        Project::query()->delete();
        Education::query()->delete();

        $this->simulate($json);
        $this->confirm();

        $this->assertSame('Montre connectée', Project::sole()->title);
        $this->assertSame('Tendances IA', Article::sole()->title);
        $this->assertSame(5, count(Article::sole()->content['blocks']));
        $this->assertSame('2024-01-01', Education::sole()->start_date->toDateString());
    }

    public function test_invalid_items_are_reported_and_skipped(): void
    {
        $this->simulate(['sections' => [
            'skills'   => [['name' => 'PHP'], ['level' => 3]],
            'projects' => [['title' => 'Sans date', 'description' => 'x']],
            'articles' => [['title' => 'Auteur inconnu', 'author' => 'personne@example.com']],
        ]]);
        $this->confirm();

        $report = session('transfer.report');
        $this->assertSame(1, $report['skills']['created']);
        $this->assertSame(1, $report['skills']['skipped']);
        $this->assertSame(1, $report['projects']['skipped']);
        $this->assertStringContainsString('personne@example.com', implode(' ', $report['articles']['errors']));
        $this->assertTrue(Article::sole()->author->is($this->admin));
        $this->assertSame(['PHP'], Skill::pluck('name')->all());
    }

    public function test_invalid_file_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.transfer.preview'), [
            'file' => UploadedFile::fake()->createWithContent('import.json', '{pas du json'),
            'sections' => ['projects'],
        ])->assertSessionHasErrors('file');

        $this->simulate(['format' => 'autre', 'sections' => []])->assertSessionHasErrors('file');
    }

    public function test_example_file_is_importable(): void
    {
        $example = $this->actingAs($this->admin)->get(route('admin.transfer.example'))->assertOk();
        $payload = json_decode($example->getContent(), true);
        $payload['sections']['articles'][0]['content_html'] = '<p>Texte</p>';

        $this->simulate($payload);
        $this->confirm();

        foreach (session('transfer.report') as $section => $line) {
            $this->assertSame(0, $line['skipped'], "Section {$section} : ".implode(' ', $line['errors']));
        }
    }

    public function test_only_admins(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.transfer.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('admin.transfer.export'), ['sections' => ['projects']])->assertForbidden();
    }

    public function test_html_converter_handles_nested_containers(): void
    {
        $content = (new HtmlToEditorJs)->convert('<div><section><h3>Titre</h3><p>A<br>B</p></section><figure><img src="/x.png"><figcaption>Légende</figcaption></figure><table><tr><th>C</th></tr><tr><td>D</td></tr></table><hr></div>');

        $this->assertSame(['header', 'paragraph', 'image', 'table', 'delimiter'], array_column($content['blocks'], 'type'));
        $this->assertSame('Légende', $content['blocks'][2]['data']['caption']);
        $this->assertTrue($content['blocks'][3]['data']['withHeadings']);
    }
}
