<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarkdownEditorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    protected function article(array $overrides = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.articles.store'), array_merge([
            'title' => 'Article Markdown', 'author_id' => $this->admin->id, 'status' => 'published',
        ], $overrides));
    }

    public function test_article_written_in_markdown_is_converted_and_rendered_like_the_visual_editor(): void
    {
        $markdown = "## Introduction\n\nDu **gras** et une <mark class=\"cdx-marker\">note</mark>.\n\n- un\n- deux\n\n> Citation";

        $this->article([
            'content'          => '{"blocks":[]}',
            'content_editor'   => 'markdown',
            'content_markdown' => $markdown,
        ])->assertSessionHasNoErrors();

        $article = Article::sole();
        $this->assertSame('markdown', $article->content_editor);
        $this->assertSame($markdown, $article->content_markdown);
        $this->assertSame(['header', 'paragraph', 'list', 'quote'], array_column($article->content['blocks'], 'type'));

        $this->get(route('articles.show', $article))
            ->assertSee('<h2 id="introduction">Introduction<a href="#introduction"', false)
            ->assertSee('<b>gras</b>', false)
            ->assertSee('<mark class="cdx-marker">note</mark>', false)
            ->assertSee('<ul><li>un</li><li>deux</li></ul>', false);

        // La source Markdown est retrouvée telle quelle à la réouverture.
        $this->actingAs($this->admin)->get(route('admin.articles.edit', $article))
            ->assertSee('Du **gras**', false)
            ->assertSee("mode: 'markdown'", false);
    }

    public function test_switching_back_to_the_visual_editor_forgets_the_markdown_source(): void
    {
        $this->article(['content_editor' => 'markdown', 'content_markdown' => 'Texte']);
        $article = Article::sole();

        $this->actingAs($this->admin)->put(route('admin.articles.update', $article), [
            'title' => 'Article Markdown', 'author_id' => $this->admin->id, 'status' => 'published',
            'content_editor' => 'blocks',
            'content'        => json_encode(['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Modifié']]]]),
        ])->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame('blocks', $article->content_editor);
        $this->assertNull($article->content_markdown);
        $this->assertSame('Modifié', $article->content['blocks'][0]['data']['text']);
    }

    public function test_project_description_in_markdown_is_required(): void
    {
        Storage::fake('local');
        $post = fn (string $markdown) => $this->actingAs($this->admin)->post(route('admin.projets.store'), [
            'title' => 'Projet', 'published_on' => '2026-01-01',
            'description_editor' => 'markdown', 'description_markdown' => $markdown,
        ]);

        $post('   ')->assertSessionHasErrors('description');
        $post("### Objectifs\n\n1. Concevoir\n2. Livrer")->assertSessionHasNoErrors();

        $project = Project::sole();
        $this->assertSame('markdown', $project->description_editor);
        $this->assertSame(['header', 'list'], array_column($project->description['blocks'], 'type'));
        $this->assertSame('Objectifs Concevoir Livrer', $project->excerpt());
    }

    public function test_conversion_endpoints(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.editor.to-markdown'), ['content' => ['blocks' => [
                ['type' => 'header', 'data' => ['text' => 'Titre', 'level' => 2]],
                ['type' => 'paragraph', 'data' => ['text' => '<b>Gras</b>']],
            ]]])
            ->assertOk()
            ->assertJsonPath('markdown', "## Titre\n\n**Gras**\n");

        $this->actingAs($this->admin)
            ->postJson(route('admin.editor.to-blocks'), ['markdown' => "## Titre\n\n**Gras**"])
            ->assertOk()
            ->assertJsonPath('content.blocks.0.type', 'header')
            ->assertJsonPath('html', "<h2 id=\"titre\">Titre<a href=\"#titre\" class=\"heading-anchor\" aria-label=\"Lien vers cette section\">#</a></h2>\n<p><b>Gras</b></p>");

        auth()->logout();
        $this->postJson(route('admin.editor.to-blocks'), ['markdown' => 'x'])->assertUnauthorized();
    }

    public function test_markdown_toggle_only_on_articles_and_projects(): void
    {
        $this->actingAs($this->admin)->get(route('admin.articles.create'))->assertSee('name="content_markdown"', false);
        $this->actingAs($this->admin)->get(route('admin.projets.create'))->assertSee('name="description_markdown"', false);
        $this->actingAs($this->admin)->get(route('admin.profile.edit'))->assertDontSee('_markdown', false)->assertSee('data-editorjs', false);
    }

    public function test_markdown_is_kept_after_a_validation_error(): void
    {
        $this->actingAs($this->admin)->from(route('admin.articles.create'))->post(route('admin.articles.store'), [
            'title' => '', 'author_id' => $this->admin->id, 'status' => 'draft',
            'content_editor' => 'markdown', 'content_markdown' => 'Mon **brouillon**',
        ])->assertSessionHasErrors('title');

        $this->actingAs($this->admin)->get(route('admin.articles.create'))
            ->assertSee('Mon **brouillon**', false)
            ->assertSee("mode: 'markdown'", false);
    }

    public function test_import_accepts_markdown(): void
    {
        Storage::fake('local');
        $payload = ['sections' => [
            'articles' => [['title' => 'Import MD', 'content_markdown' => "# Titre\n\nTexte *italique*"]],
            'projects' => [['title' => 'Projet MD', 'published_on' => '2026-01-01', 'description_markdown' => '**Gras**']],
        ]];

        $this->actingAs($this->admin)->post(route('admin.transfer.preview'), [
            'file' => UploadedFile::fake()->createWithContent('i.json', json_encode($payload)),
            'sections' => ['articles', 'projects'],
        ]);
        $this->actingAs($this->admin)->post(route('admin.transfer.import'));

        $this->assertSame('markdown', Article::sole()->content_editor);
        $this->assertSame(['header', 'paragraph'], array_column(Article::sole()->content['blocks'], 'type'));
        $this->assertSame('**Gras**', Project::sole()->description_markdown);
    }
}
