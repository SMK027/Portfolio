<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_public_pages_and_published_articles_only(): void
    {
        $author = User::factory()->admin()->create();
        $published = Article::create(['title' => 'Publié', 'author_id' => $author->id, 'content' => ['blocks' => []], 'published_at' => now()->subDay()]);
        $draft = Article::create(['title' => 'Brouillon', 'author_id' => $author->id, 'content' => ['blocks' => []]]);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('articles.show', $published), false)
            ->assertDontSee(route('articles.show', $draft), false);

        Page::where('key', 'veille')->update(['is_public' => false]);
        Page::flushCache();
        $this->get('/sitemap.xml')->assertDontSee(route('articles.show', $published), false);
    }

    public function test_robots_points_to_sitemap_only_when_indexable(): void
    {
        $this->get('/robots.txt')->assertSee('Sitemap: '.route('sitemap'));

        Setting::set(Setting::INDEXABLE, false);
        $this->get('/robots.txt')->assertDontSee('Sitemap:');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<url>', false);
    }

    public function test_public_pages_expose_canonical_open_graph_and_structured_data(): void
    {
        $this->get('/?utm_source=x')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('property="og:url"', false)
            ->assertSee('"@type":"Person"', false)
            ->assertSee('media="print" onload="this.media=\'all\'"', false);

        $author = User::factory()->admin()->create(['name' => 'Rédactrice']);
        $article = Article::create(['title' => 'Veille', 'author_id' => $author->id, 'content' => ['blocks' => [
            ['type' => 'image', 'data' => ['file' => ['url' => '/storage/a.png'], 'caption' => '']],
        ]], 'published_at' => now()->subDay()]);

        $this->get(route('articles.show', $article))->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"name":"Rédactrice"', false)
            ->assertSee('loading="lazy" decoding="async"', false);
    }
}
