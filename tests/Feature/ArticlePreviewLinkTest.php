<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticlePreviewLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Article $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->draft = Article::create(['title' => 'Brouillon secret', 'author_id' => $this->admin->id,
            'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Contenu à relire']]]]]);
    }

    protected function share(int $days = 7): string
    {
        $this->actingAs($this->admin)->post(route('admin.articles.preview.store', $this->draft), ['days' => $days])->assertRedirect();
        auth()->logout();

        return $this->draft->fresh()->previewUrl();
    }

    public function test_anyone_with_the_link_reads_the_draft_without_account(): void
    {
        $this->get(route('articles.show', $this->draft))->assertNotFound();
        $url = $this->share();

        $this->get($url)->assertOk()->assertSee('Contenu à relire')->assertSee('Aperçu d\'un brouillon partagé', false)
            ->assertSee('noindex, nofollow', false);
        $this->assertNotNull(AuditLog::where('action', 'article.preview_shared')->first());
        $this->assertStringNotContainsString($this->draft->fresh()->preview_token, AuditLog::all()->toJson());
    }

    public function test_private_veille_page_blocks_preview_links_and_their_files(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('schema.png')->store('article-files', 'local');
        $file = $this->draft->files()->create(['path' => $path, 'original_name' => 'schema.png', 'mime_type' => 'image/png', 'size' => 100]);
        $url = $this->share();
        $this->get($url)->assertOk(); // session autorisée tant que la page est publique

        Page::where('key', 'veille')->update(['is_public' => false]);
        Page::flushCache();

        $this->get($url)->assertNotFound();
        $this->get($file->url())->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.articles.show', $this->draft))->assertSee('les liens de relecture ne fonctionnent pas');
    }

    public function test_link_expires_and_can_be_revoked_or_replaced(): void
    {
        $url = $this->share(1);
        $this->travel(25)->hours();
        $this->get($url)->assertNotFound();
        $this->travelBack();

        $url = $this->share();
        $replacement = $this->share();
        $this->assertNotSame($url, $replacement);
        $this->get($url)->assertNotFound();

        $this->actingAs($this->admin)->delete(route('admin.articles.preview.destroy', $this->draft));
        auth()->logout();
        $this->get($replacement)->assertNotFound();
    }

    public function test_draft_attachments_are_readable_through_the_link_session(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('schema.png')->store('article-files', 'local');
        $file = $this->draft->files()->create(['path' => $path, 'original_name' => 'schema.png', 'mime_type' => 'image/png', 'size' => 100]);

        $this->get($file->url())->assertNotFound();
        $this->get($this->share())->assertOk();
        $this->get($file->url())->assertOk();
    }

    public function test_only_editors_can_share(): void
    {
        $this->actingAs(User::factory()->create(['global_role' => 'user']))
            ->post(route('admin.articles.preview.store', $this->draft), ['days' => 7])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.articles.preview.store', $this->draft), ['days' => 99])->assertSessionHasErrors('days');
        $this->get(route('admin.articles.show', $this->draft))->assertSee('Créer un lien de relecture');
    }
}
