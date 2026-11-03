<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_page_shows_reading_time_and_progress_bar(): void
    {
        $author = User::factory()->admin()->create();
        $article = Article::create(['title' => 'Guide', 'author_id' => $author->id, 'published_at' => now()->subDay(),
            'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => str_repeat('mot ', 450)]]]]]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee('3 min de lecture')
            ->assertSee('x-data="readingProgress"', false)
            ->assertSee('id="article-body"', false);
    }
}
