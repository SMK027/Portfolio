<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Hobby;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExperienceAndHobbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_pages_are_created_private(): void
    {
        $this->assertFalse(Page::findByKey('experiences')->is_public);
        $this->assertFalse(Page::findByKey('loisirs')->is_public);
        $this->get('/experiences')->assertNotFound();
        $this->get('/loisirs')->assertNotFound();
    }

    public function test_admin_manages_experiences(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.experiences.store'), [
            'title' => 'Développeur web', 'company' => 'TechSolutions', 'contract_type' => 'Alternance',
            'date_precision' => 'month', 'start_date' => '2025-09', 'ongoing' => '1',
            'description' => 'Refonte du site vitrine.',
        ])->assertRedirect(route('admin.experiences.index'));

        $experience = Experience::sole();
        $this->assertNull($experience->end_date);
        $this->assertTrue($experience->isOngoing());

        Page::where('key', 'experiences')->update(['is_public' => true]);
        Page::flushCache();
        auth()->logout();

        $this->get('/experiences')->assertOk()
            ->assertSee('Développeur web')->assertSee('TechSolutions')
            ->assertSee('Alternance')->assertSee('septembre 2025 — aujourd')->assertSee('En poste');

        $this->actingAs($admin)->delete(route('admin.experiences.destroy', $experience));
        $this->assertDatabaseCount('experiences', 0);
    }

    public function test_admin_manages_hobbies_with_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.loisirs.store'), [
            'name' => 'Photographie', 'description' => 'Paysages urbains.',
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertRedirect(route('admin.loisirs.index'));

        $hobby = Hobby::sole();
        Storage::disk('public')->assertExists($hobby->image_path);

        Page::where('key', 'loisirs')->update(['is_public' => true]);
        Page::flushCache();
        $this->get('/loisirs')->assertOk()->assertSee('Photographie')->assertSee('Paysages urbains.');

        $this->actingAs($admin)->delete(route('admin.loisirs.destroy', $hobby));
        Storage::disk('public')->assertMissing($hobby->image_path);
    }
}
