<?php

namespace Tests\Feature;

use App\Models\ServiceToken;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoginPath;
use App\Services\Maintenance;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

class LoginPathTest extends TestCase
{
    use RefreshDatabase;

    /** Les routes sont enregistrées au démarrage : on les recharge après un changement d'adresse. */
    protected function reloadRoutes(): void
    {
        $router = app('router');
        $router->setRoutes(new RouteCollection);
        foreach (app()->getProviders(RouteServiceProvider::class) as $provider) {
            (fn () => $this->loadRoutes())->call($provider);
        }
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
        app('url')->setRoutes($router->getRoutes());
    }

    protected function moveLoginTo(string $path): void
    {
        LoginPath::set($path);
        $this->reloadRoutes();
    }

    public function test_login_is_at_default_address(): void
    {
        $this->assertSame('login', LoginPath::current());
        $this->assertFalse(LoginPath::isCustom());
        $this->get('/login')->assertOk();
        $this->get('/')->assertSee('Espace administrateur');
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_custom_address_moves_password_key_and_bot_logins(): void
    {
        $this->moveLoginTo('acces-prive');

        $this->assertSame(url('acces-prive'), route('login'));
        $this->assertSame(url('acces-prive/bot'), route('login.bot'));
        $this->assertSame(url('acces-prive/cle'), route('login.key'));
        $this->assertSame(url('acces-prive/cle/options'), route('login.key.options'));

        $this->get('/acces-prive')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/acces-prive/bot')->assertOk();
        $this->postJson('/acces-prive/cle/options')->assertOk();

        foreach (['/login', '/login/bot'] as $old) {
            $this->get($old)->assertNotFound();
        }
        $this->post('/login/cle/options')->assertNotFound();

        $admin = User::factory()->admin()->create();
        $this->post('/acces-prive', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_bot_logs_in_at_custom_address(): void
    {
        $bot = User::factory()->create(['global_role' => 'bot']);
        [, $plain] = ServiceToken::issue($bot, 'Poste');

        $this->moveLoginTo('acces-prive');

        $this->post('/login/bot', ['code' => $plain])->assertNotFound();
        $this->post('/acces-prive/bot', ['code' => $plain])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($bot);
    }

    public function test_custom_address_is_not_revealed_to_guests(): void
    {
        $this->moveLoginTo('acces-prive');

        $this->get('/')->assertOk()->assertDontSee('Espace administrateur')->assertDontSee('acces-prive');
        $this->get(route('admin.dashboard'))->assertNotFound();
        $this->get('/profile')->assertNotFound();

        app(Maintenance::class)->enable();
        $this->get('/')->assertSee('Site en maintenance')->assertDontSee('acces-prive');
        // La connexion reste accessible pendant la maintenance
        $this->get('/acces-prive')->assertOk()->assertDontSee('Site en maintenance');
    }

    public function test_api_is_unchanged(): void
    {
        $this->moveLoginTo('acces-prive');

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_super_admin_changes_the_address_from_the_panel(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('admin.login-path.edit'))->assertOk()->assertSee(url('login'));

        $this->put(route('admin.login-path.update'), ['path' => 'acces', 'current_password' => 'mauvais'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame('login', LoginPath::configured());

        $this->put(route('admin.login-path.update'), ['path' => '/Acces-Prive/', 'current_password' => 'password'])
            ->assertRedirect(route('admin.login-path.edit'))->assertSessionHas('success');
        $this->assertSame('acces-prive', LoginPath::configured());
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'Setting', 'subject_label' => 'Adresse de la page de connexion']);

        // Champ vide : retour à /login
        $this->put(route('admin.login-path.update'), ['path' => '', 'current_password' => 'password']);
        $this->assertSame('login', LoginPath::configured());
        $this->assertNull(Setting::query()->find(LoginPath::SETTING)?->value);
    }

    public function test_invalid_or_taken_addresses_are_refused(): void
    {
        $super = User::factory()->superAdmin()->create();

        foreach (['abc', 'accès', 'a b c d', 'admin/connexion', 'api/login', 'projets', 'build', 'storage', 'favicon.ico', 'up'] as $path) {
            $this->actingAs($super)->put(route('admin.login-path.update'), ['path' => $path, 'current_password' => 'password'])
                ->assertSessionHasErrors('path');
        }
        $this->assertSame('login', LoginPath::configured());
    }

    public function test_only_super_admins_can_change_the_address(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.login-path.edit'))->assertForbidden();
        $this->put(route('admin.login-path.update'), ['path' => 'acces-prive', 'current_password' => 'password'])->assertForbidden();
        $this->assertSame('login', LoginPath::configured());
    }

    public function test_command_shows_sets_and_resets_the_address(): void
    {
        $this->artisan('portfolio:login-path')->expectsOutputToContain(url('login'))->assertSuccessful();
        $this->artisan('portfolio:login-path', ['path' => 'admin'])->assertFailed();
        $this->artisan('portfolio:login-path', ['path' => 'acces-prive'])->assertSuccessful();
        $this->assertSame('acces-prive', LoginPath::configured());
        $this->artisan('portfolio:login-path', ['--reset' => true])->assertSuccessful();
        $this->assertSame('login', LoginPath::configured());
    }
}
