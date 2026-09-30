<?php

namespace Tests\Feature;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_theme_from_a_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('admin.themes.quick'), ['name' => 'Réseau'])
            ->assertCreated()
            ->assertJson(['name' => 'Réseau']);

        $this->assertSame('reseau', Theme::sole()->slug);
    }

    public function test_existing_theme_is_reused_case_insensitively(): void
    {
        $admin = User::factory()->admin()->create();
        $theme = Theme::create(['name' => 'Programmation']);

        $this->actingAs($admin)->postJson(route('admin.themes.quick'), ['name' => '  programmation '])
            ->assertOk()
            ->assertJson(['id' => $theme->id]);

        $this->assertDatabaseCount('themes', 1);
    }

    public function test_name_is_required_and_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('admin.themes.quick'), ['name' => ''])->assertUnprocessable();

        $this->actingAs(User::factory()->create())->postJson(route('admin.themes.quick'), ['name' => 'X'])->assertForbidden();
    }

    public function test_forms_offer_the_create_button(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.projets.create'))->assertSee('Nouveau thème');
        $this->actingAs($admin)->get(route('admin.articles.create'))->assertSee('Nouveau thème');
    }
}
