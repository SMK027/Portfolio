<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorJsRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTableOfContentsTest extends TestCase
{
    use RefreshDatabase;

    protected function content(array $headings): array
    {
        $blocks = [];
        foreach ($headings as [$level, $text]) {
            $blocks[] = ['type' => 'header', 'data' => ['level' => $level, 'text' => $text]];
            $blocks[] = ['type' => 'paragraph', 'data' => ['text' => 'Texte de la section.']];
        }

        return ['blocks' => $blocks];
    }

    public function test_headings_get_unique_readable_ids(): void
    {
        $content = $this->content([[2, 'Mise en place'], [3, 'Étape <b>1</b> : l\'installation'], [2, 'Mise en place'], [5, 'Détail'], [2, '']]);
        $renderer = app(EditorJsRenderer::class);

        $this->assertSame([
            ['level' => 2, 'text' => 'Mise en place', 'id' => 'mise-en-place'],
            ['level' => 3, 'text' => 'Étape 1 : l\'installation', 'id' => 'etape-1-linstallation'],
            ['level' => 2, 'text' => 'Mise en place', 'id' => 'mise-en-place-2'],
        ], $renderer->headings($content));

        $html = $renderer->render($content);
        $this->assertStringContainsString('<h2 id="mise-en-place">Mise en place<a href="#mise-en-place" class="heading-anchor"', $html);
        $this->assertStringContainsString('<h3 id="etape-1-linstallation">Étape <b>1</b>', $html);
        $this->assertStringContainsString('<h5 id="detail">', $html);
    }

    public function test_article_page_shows_table_of_contents_from_two_headings(): void
    {
        $author = User::factory()->admin()->create();
        $article = Article::create(['title' => 'Guide', 'author_id' => $author->id, 'published_at' => now()->subDay(),
            'content' => $this->content([[2, 'Introduction'], [3, 'Contexte'], [2, 'Conclusion']])]);

        $this->get(route('articles.show', $article))->assertOk()
            ->assertSee('aria-label="Sommaire"', false)
            ->assertSee('href="#introduction"', false)
            ->assertSee('href="#contexte"', false)
            ->assertSee('id="conclusion"', false);

        $article->update(['content' => $this->content([[2, 'Seul titre']])]);
        $this->get(route('articles.show', $article))->assertOk()->assertDontSee('aria-label="Sommaire"', false);
    }

    public function test_tree_nests_subheadings_and_page_offers_folding(): void
    {
        $author = User::factory()->admin()->create();
        $article = Article::create(['title' => 'Guide', 'author_id' => $author->id, 'published_at' => now()->subDay(),
            'content' => $this->content([[2, 'A'], [3, 'A1'], [4, 'A1a'], [3, 'A2'], [2, 'B'], [4, 'B-saut'], [2, 'C']])]);

        $tree = $article->tableOfContentsTree();
        $this->assertSame(['a', 'b', 'c'], array_column($tree, 'id'));
        $this->assertSame(['a1', 'a2'], array_column($tree[0]['children'], 'id'));
        $this->assertSame(['a1a'], array_column($tree[0]['children'][0]['children'], 'id'));
        $this->assertSame(['b-saut'], array_column($tree[1]['children'], 'id')); // niveau sauté rattaché à B
        $this->assertSame([], $tree[2]['children']);

        $this->get(route('articles.show', $article))->assertOk()
            ->assertSee('Tout replier')
            ->assertSee("toggle('a')", false)
            ->assertDontSee("toggle('c')", false);
    }
}
