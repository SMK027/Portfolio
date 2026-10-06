<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! ImageOptimizer::supportsWebp()) {
            $this->markTestSkipped('GD sans prise en charge du WebP.');
        }
    }

    /** Image « photo » (dégradé + bruit) : réaliste pour la compression. */
    protected function photo(int $width, int $height, string $format = 'jpeg', bool $alpha = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($alpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagefilledellipse($image, intdiv($width, 2), intdiv($height, 2), intdiv($width, 2), intdiv($height, 2), imagecolorallocatealpha($image, 200, 30, 30, 0));
        } else {
            for ($y = 0; $y < $height; $y += 4) {
                imagefilledrectangle($image, 0, $y, $width, $y + 3, imagecolorallocate($image, ($y * 7) % 256, ($y * 3) % 256, random_int(0, 255)));
            }
        }
        ob_start();
        $format === 'png' ? imagepng($image) : imagejpeg($image, null, 95);

        return ob_get_clean();
    }

    public function test_large_photo_is_resized_and_converted_to_webp(): void
    {
        $original = $this->photo(4000, 3000);
        $result = app(ImageOptimizer::class)->optimize($original);

        $this->assertSame('webp', $result['extension']);
        $this->assertSame('image/webp', $result['mime']);
        [$width, $height] = getimagesizefromstring($result['binary']);
        $this->assertSame([1920, 1440], [$width, $height]);
        $this->assertLessThan(strlen($original) / 3, strlen($result['binary']));
    }

    public function test_png_transparency_is_kept(): void
    {
        $result = app(ImageOptimizer::class)->optimize($this->photo(2400, 1200, 'png', alpha: true));

        $image = imagecreatefromstring($result['binary']);
        $this->assertSame(127, imagecolorsforindex($image, imagecolorat($image, 5, 5))['alpha']); // coin transparent
        $this->assertSame(0, imagecolorsforindex($image, imagecolorat($image, 960, 480))['alpha']); // centre opaque
    }

    public function test_gifs_and_non_images_are_left_untouched(): void
    {
        $gif = imagecreatetruecolor(10, 10);
        ob_start();
        imagegif($gif);
        $optimizer = app(ImageOptimizer::class);

        $this->assertNull($optimizer->optimize(ob_get_clean()));
        $this->assertNull($optimizer->optimize('%PDF-1.4 pas une image'));
    }

    public function test_small_already_compact_image_is_kept(): void
    {
        $tiny = imagecreatetruecolor(40, 40);
        ob_start();
        imagewebp($tiny, null, 50);

        $this->assertNull(app(ImageOptimizer::class)->optimize(ob_get_clean()));
    }

    public function test_editor_upload_is_stored_as_webp(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post(route('admin.uploads.image'), [
            'image' => UploadedFile::fake()->createWithContent('vacances.jpg', $this->photo(3000, 2000)),
        ])->assertOk()->assertJsonPath('success', 1);

        $path = str_replace('/storage/', '', $response->json('file.url'));
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(1920, getimagesizefromstring(Storage::disk('public')->get($path))[0]);
    }

    public function test_article_images_are_converted_and_renamed(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.articles.store'), [
            'title' => 'Galerie', 'author_id' => $admin->id, 'status' => 'published',
            'files' => [
                UploadedFile::fake()->createWithContent('photo.jpg', $this->photo(2500, 1500)),
                UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        [$image, $document] = Article::sole()->files->sortBy('position')->values()->all();
        $this->assertSame('photo.webp', $image->original_name);
        $this->assertSame('image/webp', $image->mime_type);
        $this->assertStringEndsWith('.webp', $image->path);
        $this->assertSame(strlen(Storage::disk('local')->get($image->path)), $image->size);
        $this->assertSame('notes.pdf', $document->original_name); // documents inchangés
    }
}
