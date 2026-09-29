<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleFileTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    protected function createArticle(array $overrides = []): Article
    {
        $this->actingAs($this->admin)->post(route('admin.articles.store'), array_merge([
            'title'     => 'Retour sur une conférence',
            'author_id' => $this->admin->id,
            'status'    => 'published',
        ], $overrides))->assertSessionHasNoErrors();

        return Article::latest('id')->first();
    }

    public function test_admin_attaches_images_and_documents_to_an_article(): void
    {
        $article = $this->createArticle(['files' => [
            UploadedFile::fake()->image('photo-1.jpg', 1200, 800),
            UploadedFile::fake()->image('photo-2.png', 1200, 800),
            UploadedFile::fake()->create('slides.pptx', 120),
            UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
        ]]);

        $this->assertCount(4, $article->files);
        $this->assertCount(2, $article->images());
        $this->assertCount(2, $article->documents());

        auth()->logout();
        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee('aria-roledescription="carrousel"', false)
            ->assertSee('Images de l\'article')
            ->assertSee('slides.pptx')
            ->assertSee('notes.pdf');

        $image = $article->images()->first();
        $this->get($image->url())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get($article->documents()->first()->downloadUrl())->assertOk()->assertDownload('slides.pptx');
    }

    public function test_attachments_can_be_removed_and_are_deleted_from_disk(): void
    {
        $article = $this->createArticle(['files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]);
        [$first, $second] = $article->files;

        $this->actingAs($this->admin)->put(route('admin.articles.update', $article), [
            'title'        => $article->title,
            'author_id'    => $this->admin->id,
            'status'       => 'published',
            'delete_files' => [$first->id],
        ])->assertSessionHasNoErrors();

        $this->assertModelMissing($first);
        Storage::disk(ArticleFile::DISK)->assertMissing($first->path);
        Storage::disk(ArticleFile::DISK)->assertExists($second->path);

        $this->actingAs($this->admin)->delete(route('admin.articles.destroy', $article));
        $this->assertDatabaseCount('article_files', 0);
        Storage::disk(ArticleFile::DISK)->assertMissing($second->path);
    }

    public function test_draft_attachments_are_only_served_to_admins(): void
    {
        $article = $this->createArticle(['status' => 'draft', 'files' => [UploadedFile::fake()->image('secret.jpg')]]);
        $url = $article->files->first()->url();

        $this->get($url)->assertOk();
        auth()->logout();
        $this->get($url)->assertNotFound();
    }

    public function test_attachments_follow_page_visibility(): void
    {
        $article = $this->createArticle(['files' => [UploadedFile::fake()->image('a.jpg')]]);
        $url = $article->files->first()->url();
        auth()->logout();

        Page::where('key', 'veille')->update(['is_public' => false]);
        Page::flushCache();

        $this->get($url)->assertNotFound();
    }

    public function test_invalid_attachments_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.articles.store'), [
            'title' => 'X', 'author_id' => $this->admin->id, 'status' => 'draft',
            'files' => [
                UploadedFile::fake()->create('script.php', 1),
                UploadedFile::fake()->createWithContent('image.png', '<html><script>alert(1)</script></html>'),
            ],
        ])->assertSessionHasErrors(['files.0', 'files.1']);

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_files_of_another_article_cannot_be_reached(): void
    {
        $a = $this->createArticle(['title' => 'A', 'files' => [UploadedFile::fake()->image('a.jpg')]]);
        $b = $this->createArticle(['title' => 'B']);

        $this->get(route('articles.files.show', [$b->id, $a->files->first()->id]))->assertNotFound();
    }
}
