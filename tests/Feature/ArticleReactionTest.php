<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleReaction;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleReactionTest extends TestCase
{
    use RefreshDatabase;

    protected function article(array $attributes = []): Article
    {
        return Article::create($attributes + [
            'title' => 'Réagir', 'author_id' => User::factory()->admin()->create()->id, 'published_at' => now()->subDay(),
        ]);
    }

    public function test_visitor_toggles_a_reaction(): void
    {
        $article = $this->article();
        $url = route('articles.reactions', $article);

        $this->postJson($url, ['emoji' => 'fire'])->assertOk()
            ->assertJsonPath('counts.fire', 1)->assertJsonPath('mine', ['fire']);
        $this->postJson($url, ['emoji' => 'like'])->assertOk()
            ->assertJsonPath('counts.like', 1)->assertJsonPath('mine', ['fire', 'like']);
        $this->postJson($url, ['emoji' => 'fire'])->assertOk()
            ->assertJsonPath('counts.fire', 0)->assertJsonPath('mine', ['like']);

        $this->assertDatabaseCount('article_reactions', 1);
    }

    public function test_each_visitor_counts_once_per_emoji(): void
    {
        $article = $this->article();
        $url = route('articles.reactions', $article);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->postJson($url, ['emoji' => 'love']);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->postJson($url, ['emoji' => 'love'])
            ->assertJsonPath('counts.love', 2)->assertJsonPath('mine', ['love']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.3'])->get(route('articles.show', $article))
            ->assertOk()->assertSee('Cet article vous a plu ?');
    }

    public function test_invalid_emoji_drafts_and_bots_are_rejected(): void
    {
        $article = $this->article();

        $this->postJson(route('articles.reactions', $article), ['emoji' => 'poop'])->assertUnprocessable();
        $this->postJson(route('articles.reactions', $article), ['emoji' => 'like', 'website' => 'x'])->assertUnprocessable();

        $draft = $this->article(['title' => 'Brouillon', 'published_at' => null]);
        $this->postJson(route('articles.reactions', $draft), ['emoji' => 'like'])->assertNotFound();

        $this->assertDatabaseCount('article_reactions', 0);
    }

    public function test_private_veille_page_blocks_reactions(): void
    {
        $article = $this->article();
        Page::where('key', 'veille')->update(['is_public' => false]);
        Page::flushCache();

        $this->postJson(route('articles.reactions', $article), ['emoji' => 'like'])->assertNotFound();
    }

    public function test_reactions_are_deleted_with_the_article(): void
    {
        $article = $this->article();
        ArticleReaction::create(['article_id' => $article->id, 'emoji' => 'like', 'visitor' => str_repeat('a', 64)]);

        $article->delete();

        $this->assertDatabaseCount('article_reactions', 0);
    }
}
