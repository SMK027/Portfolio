<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RemoteImageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Images collées dans l'éditeur (point « byUrl » de l'outil image).
 */
class EditorPasteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    protected function pngDataUri(): string
    {
        return 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('x.png', 20, 20)->getContent());
    }

    public function test_pasted_base64_image_is_stored(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.uploads.image-url'), ['url' => $this->pngDataUri()])
            ->assertOk()
            ->assertJsonPath('success', 1);

        $url = $response->json('file.url');
        $this->assertStringStartsWith('/storage/editor/', $url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    }

    public function test_non_image_content_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.uploads.image-url'), ['url' => 'data:image/png;base64,'.base64_encode('<svg onload="alert(1)"></svg>')])
            ->assertStatus(422)
            ->assertJsonPath('success', 0);
    }

    public function test_images_already_on_the_site_are_kept_as_is(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.uploads.image-url'), ['url' => 'http://localhost/veille/3/fichiers/5'])
            ->assertJsonPath('file.url', '/veille/3/fichiers/5');

        $this->actingAs($this->admin)
            ->postJson(route('admin.uploads.image-url'), ['url' => '/storage/editor/a.png'])
            ->assertJsonPath('file.url', '/storage/editor/a.png');
    }

    /** @return array<string, array{string}> */
    public static function internalUrls(): array
    {
        return [
            'boucle locale'        => ['https://127.0.0.1/image.png'],
            'réseau privé'         => ['https://10.0.0.5/image.png'],
            'métadonnées cloud'    => ['http://169.254.169.254/latest/meta-data'],
            'IPv6 locale'          => ['https://[::1]/image.png'],
            'IPv4 dans IPv6'       => ['https://[::ffff:127.0.0.1]/image.png'],
            'CGNAT'                => ['https://100.64.0.1/image.png'],
            'schéma file'          => ['file:///etc/passwd'],
            'port non standard'    => ['https://example.com:8080/image.png'],
        ];
    }

    #[DataProvider('internalUrls')]
    public function test_internal_addresses_are_refused_without_fallback(string $url): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.uploads.image-url'), ['url' => $url])
            ->assertStatus(422)
            ->assertJsonPath('success', 0);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_public_ip_detection(): void
    {
        $fetcher = app(RemoteImageFetcher::class);

        $this->assertTrue($fetcher->isPublicIp('93.184.216.34'));
        $this->assertTrue($fetcher->isPublicIp('2606:4700:4700::1111'));
        $this->assertFalse($fetcher->isPublicIp('192.168.1.10'));
        $this->assertFalse($fetcher->isPublicIp('172.20.0.14'));
        $this->assertFalse($fetcher->isPublicIp('::ffff:10.0.0.1'));
        $this->assertFalse($fetcher->isPublicIp('100.100.0.1'));
    }

    public function test_only_admins_can_use_the_endpoint(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.uploads.image-url'), ['url' => $this->pngDataUri()])
            ->assertForbidden();
    }

    public function test_article_gallery_offers_insertion_into_the_text(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin)->post(route('admin.articles.store'), [
            'title' => 'Article', 'author_id' => $this->admin->id, 'status' => 'draft',
            'files' => [UploadedFile::fake()->image('schema.png')],
        ]);

        $this->actingAs($this->admin)->get(route('admin.articles.edit', \App\Models\Article::sole()))
            ->assertSee('Insérer dans le texte')
            ->assertSee('editor-insert-image');
    }
}
