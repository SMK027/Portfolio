<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function adminPages(): array
    {
        return array_map(fn ($route) => [$route], [
            'admin.dashboard', 'admin.profile.edit', 'admin.pages.index',
            'admin.formations.index', 'admin.formations.create',
            'admin.experiences.index', 'admin.experiences.create',
            'admin.loisirs.index', 'admin.loisirs.create',
            'admin.diplomes.index', 'admin.diplomes.create',
            'admin.certifications.index', 'admin.certifications.create',
            'admin.competences.index', 'admin.competences.create',
            'admin.themes.index', 'admin.themes.create',
            'admin.projets.index', 'admin.projets.create',
            'admin.articles.index', 'admin.articles.create',
            'admin.messages.index', 'admin.utilisateurs.index',
        ]);
    }

    #[DataProvider('adminPages')]
    public function test_guests_are_redirected_to_login(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    #[DataProvider('adminPages')]
    public function test_non_admin_users_are_forbidden(string $route): void
    {
        $this->actingAs(User::factory()->create())->get(route($route))->assertForbidden();
    }

    #[DataProvider('adminPages')]
    public function test_admins_can_open_admin_pages(string $route): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route($route))->assertOk();
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_only_super_admins_manage_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.utilisateurs.create'))->assertForbidden();

        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super)->post(route('admin.utilisateurs.store'), [
            'name'                  => 'Co Auteur',
            'username'              => 'coauteur',
            'email'                 => 'co@example.com',
            'global_role'           => 'user',
            'password'              => 'Secret-password-123',
            'password_confirmation' => 'Secret-password-123',
        ])->assertRedirect(route('admin.utilisateurs.index'));

        $this->assertDatabaseHas('users', ['email' => 'co@example.com', 'global_role' => 'user']);
    }

    public function test_dashboard_redirects_according_to_role(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertRedirect(route('admin.dashboard'));
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect(route('profile.edit'));
    }
}
