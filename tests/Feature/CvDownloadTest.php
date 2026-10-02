<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CvDownloadTest extends TestCase
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

    protected function upload(array $extra = []): void
    {
        $this->actingAs($this->admin)->put(route('admin.profile.update'), [
            'first_name' => 'Léo', 'last_name' => 'Franz', 'cv_downloadable' => '1',
            'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'), ...$extra,
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    public function test_cv_is_stored_privately_and_served_by_the_cv_route(): void
    {
        $this->upload();
        $profile = Profile::current()->fresh();

        $this->assertStringStartsWith('cv/', $profile->cv_path);
        Storage::disk('local')->assertExists($profile->cv_path);
        $this->assertSame([], Storage::disk('public')->allFiles()); // rien dans le dossier public

        $this->get(route('home'))->assertSee(route('cv.download'));
        $response = $this->get(route('cv.download'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('CV%20-%20L%C3%A9o%20Franz.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_blocking_hides_the_button_and_the_route(): void
    {
        $this->upload();
        $this->actingAs($this->admin)->put(route('admin.profile.update'), ['first_name' => 'Léo', 'last_name' => 'Franz'])->assertSessionHasNoErrors();
        auth()->logout();

        $this->assertFalse(Profile::current()->fresh()->cv_downloadable);
        $this->get(route('home'))->assertDontSee('Mon CV');
        $this->get(route('cv.download'))->assertNotFound();

        // Le panel garde l'aperçu.
        $this->actingAs($this->admin)->get(route('admin.profile.cv'))->assertOk();
        $this->get(route('admin.profile.edit'))->assertSee('Téléchargement bloqué');
    }

    public function test_removing_the_cv_deletes_the_file(): void
    {
        $this->upload();
        $path = Profile::current()->fresh()->cv_path;
        $this->upload(['cv' => null, 'remove_cv' => '1']);

        Storage::disk('local')->assertMissing($path);
        $this->get(route('cv.download'))->assertNotFound();
    }
}
