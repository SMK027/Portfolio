<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Page Veille privée : aucun article n'est consultable par un visiteur, par aucun chemin. */
class PrivateVeillePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_article_is_reachable_when_the_veille_page_is_private(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $article = Article::create(['title' => 'Article confidentiel', 'author_id' => $admin->id, 'published_at' => now()->subDay(),
            'excerpt' => 'Résumé confidentiel', 'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Texte confidentiel']]]]]);
        $file = $article->files()->create(['path' => UploadedFile::fake()->image('a.png')->store('article-files', 'local'),
            'original_name' => 'a.png', 'mime_type' => 'image/png', 'size' => 10]);

        Page::where('key', 'veille')->update(['is_public' => false]);
        Page::flushCache();

        $this->get(route('articles.index'))->assertNotFound();
        $this->get(route('articles.show', $article))->assertNotFound();
        $this->get($file->url())->assertNotFound();
        $this->get(route('home'))->assertDontSee('Article confidentiel');
        $this->get(route('search', ['q' => 'confidentiel']))->assertDontSee('Article confidentiel');
        $this->getJson(route('search', ['q' => 'confidentiel']))->assertJsonCount(0, 'results');
        $this->get('/sitemap.xml')->assertDontSee(route('articles.show', $article), false);
        $article->sharePreview(7);
        $this->get($article->previewUrl())->assertNotFound();

        // Contributeur (pas administrateur) : rien côté public non plus ; la rédaction reste possible dans le panel.
        $contributor = User::factory()->create(['global_role' => 'user']);
        $this->actingAs($contributor)->get(route('articles.show', $article))->assertNotFound();
        $this->get(route('admin.articles.index'))->assertOk();

        // Administrateur : aperçu conservé.
        $this->actingAs($admin)->get(route('articles.show', $article))->assertOk()->assertSee('Texte confidentiel');
    }
}
