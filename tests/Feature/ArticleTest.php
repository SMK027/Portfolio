<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function article(User $author, array $attributes): Article
    {
        return Article::create(array_merge(['author_id' => $author->id, 'content' => ['blocks' => []]], $attributes));
    }

    public function test_listing_shows_pinned_first_then_newest_and_hides_drafts(): void
    {
        $author = User::factory()->admin()->create();
        $this->article($author, ['title' => 'Ancien article', 'published_at' => now()->subDays(10)]);
        $this->article($author, ['title' => 'Article récent', 'published_at' => now()->subDay()]);
        $this->article($author, ['title' => 'Article épinglé', 'published_at' => now()->subDays(30), 'is_pinned' => true]);
        $this->article($author, ['title' => 'Brouillon caché', 'published_at' => null]);
        $this->article($author, ['title' => 'Article programmé', 'published_at' => now()->addDay()]);

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSeeInOrder(['Article épinglé', 'Article récent', 'Ancien article'])
            ->assertDontSee('Brouillon caché')
            ->assertDontSee('Article programmé');
    }

    public function test_drafts_are_only_previewable_by_admins(): void
    {
        $author = User::factory()->admin()->create();
        $draft = $this->article($author, ['title' => 'Brouillon', 'published_at' => null]);

        $this->get(route('articles.show', $draft))->assertNotFound();
        $this->actingAs($author)->get(route('articles.show', $draft))->assertOk()->assertSee('aperçu administrateur');
    }

    public function test_admin_creates_article_with_coauthors_themes_and_editor_content(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $coauthor = User::factory()->create(['name' => 'Grace Hopper']);
        $theme = Theme::create(['name' => 'Cybersécurité']);

        $content = json_encode(['time' => 1, 'version' => '2.31', 'blocks' => [
            ['type' => 'header', 'data' => ['text' => 'Introduction', 'level' => 2]],
            ['type' => 'paragraph', 'data' => ['text' => 'Texte <font color="#dc2626">coloré</font><script>alert(1)</script>']],
        ]]);

        $this->actingAs($admin)->post(route('admin.articles.store'), [
            'title'     => 'Les attaques par rebond',
            'excerpt'   => 'Résumé',
            'content'   => $content,
            'author_id' => $admin->id,
            'coauthors' => [$coauthor->id],
            'themes'    => [$theme->id],
            'status'    => 'published',
            'is_pinned' => '1',
            'thumbnail' => UploadedFile::fake()->image('mini.jpg'),
        ])->assertSessionHasNoErrors();

        $article = Article::sole();
        $this->assertTrue($article->isPublished());
        $this->assertTrue($article->is_pinned);
        $this->assertTrue($article->coauthors->contains($coauthor));
        $this->assertTrue($article->themes->contains($theme));

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertSee('<h2 id="introduction">Introduction<a href="#introduction"', false)
            ->assertSee('color="#dc2626"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_author_cannot_also_be_coauthor(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.articles.store'), [
            'title' => 'X', 'author_id' => $admin->id, 'coauthors' => [$admin->id], 'status' => 'draft',
        ])->assertSessionHasErrors('coauthors.0');
    }

    public function test_editor_image_upload_endpoint(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.uploads.image'), ['image' => UploadedFile::fake()->image('illu.png')])
            ->assertOk()
            ->assertJsonPath('success', 1);

        $this->actingAs($admin)
            ->post(route('admin.uploads.image'), ['image' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')])
            ->assertStatus(422);

        auth()->logout();
        $this->post(route('admin.uploads.image'), ['image' => UploadedFile::fake()->image('illu.png')])
            ->assertRedirect(route('login'));
    }
}
