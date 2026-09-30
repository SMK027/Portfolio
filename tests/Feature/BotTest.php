<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\ServiceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotTest extends TestCase
{
    use RefreshDatabase;

    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->superAdmin()->create();
    }

    /** @return array{0: User, 1: ServiceToken, 2: string} */
    protected function createBot(array $permissions = ['projects.read']): array
    {
        $this->actingAs($this->super)->post(route('admin.service-accounts.store'), [
            'type' => 'bot', 'name' => 'Robot de veille', 'is_active' => '1', 'permissions' => $permissions,
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $bot = User::where('global_role', 'bot')->latest('id')->first();
        [$token, $plain] = ServiceToken::issue($bot, 'Poste de travail');

        return [$bot, $token, $plain];
    }

    protected function loginBot(string $plain): void
    {
        $this->post(route('login.bot'), ['code' => $plain])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_bot_is_created_with_bot_role(): void
    {
        [$bot] = $this->createBot();

        $this->assertTrue($bot->isBot());
        $this->assertStringStartsWith('bot-', $bot->username);
        $this->actingAs($this->super)->get(route('admin.service-accounts.show', $bot))
            ->assertOk()->assertSee('Connexion du bot')->assertDontSee('Documentation de');
    }

    public function test_bot_logs_in_with_code_and_only_sees_granted_sections(): void
    {
        [, , $plain] = $this->createBot(['projects.read']);

        $this->loginBot($plain);
        $this->get(route('dashboard'))->assertRedirect(route('admin.projets.index'));
        $this->get(route('admin.projets.index'))->assertOk()->assertSee('Sections autorisées')->assertDontSee('Nouveau projet');
        $this->get(route('admin.projets.edit', \App\Models\Project::create(['title' => 'P', 'published_on' => now(), 'description' => ['blocks' => []]])))->assertOk()->assertSee('Lecture seule');

        $this->get(route('admin.projets.create'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.messages.index'))->assertForbidden();
        $this->get(route('admin.articles.index'))->assertForbidden();
        $this->get(route('admin.service-accounts.index'))->assertForbidden();
        $this->get(route('profile.edit'))->assertForbidden();
    }

    public function test_bot_without_permissions_lands_on_idle_page(): void
    {
        [, , $plain] = $this->createBot([]);

        $this->loginBot($plain);
        $this->get(route('dashboard'))->assertRedirect(route('bot.idle'));
    }

    public function test_bot_article_permissions(): void
    {
        [$bot, , $plain] = $this->createBot(['articles.read', 'articles.write']);
        $article = Article::create(['title' => 'Veille', 'author_id' => $this->super->id, 'content' => ['blocks' => []]]);

        $this->loginBot($plain);
        $this->get(route('admin.articles.index'))->assertOk();
        $this->assertTrue($bot->can('update', $article));
        $this->assertFalse($bot->can('publish', $article));
        $this->assertFalse($bot->can('delete', $article));
    }

    public function test_password_login_and_api_are_refused_to_bots(): void
    {
        [$bot, , $plain] = $this->createBot();
        $bot->forceFill(['password' => 'Connu-2026'])->save();

        $this->post('/login', ['email' => $bot->email, 'password' => 'Connu-2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$plain])->assertUnauthorized();
    }

    public function test_invalid_code_is_refused_and_logged(): void
    {
        $this->post(route('login.bot'), ['code' => 'pfs_inconnu'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNotNull(AuditLog::where('action', 'auth.failed')->first());
    }

    public function test_service_account_code_cannot_open_a_bot_session(): void
    {
        $this->actingAs($this->super)->post(route('admin.service-accounts.store'), [
            'type' => 'service', 'name' => 'API', 'is_active' => '1', 'permissions' => [],
        ]);
        auth()->logout();
        [, $plain] = ServiceToken::issue(User::where('global_role', 'service')->first(), 'API');

        $this->post(route('login.bot'), ['code' => $plain])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_disabling_the_code_ends_the_session_immediately(): void
    {
        [, $token, $plain] = $this->createBot();
        $this->loginBot($plain);
        $this->get(route('admin.projets.index'))->assertOk();

        $token->update(['disabled_at' => now()]);

        $this->get(route('admin.projets.index'))->assertRedirect(route('login.bot'));
        $this->assertGuest();
        $this->assertNotNull(AuditLog::where('action', 'auth.session_revoked')->first());
        $this->post(route('login.bot'), ['code' => $plain])->assertSessionHasErrors('code');

        // Réactivé : le code fonctionne de nouveau.
        $token->update(['disabled_at' => null]);
        $this->loginBot($plain);
    }

    public function test_deleting_the_code_ends_the_session_immediately(): void
    {
        [, $token, $plain] = $this->createBot();
        $this->loginBot($plain);

        $token->delete();

        $this->get(route('admin.projets.index'))->assertRedirect(route('login.bot'));
        $this->assertGuest();
    }

    public function test_disabling_the_bot_account_ends_the_session(): void
    {
        [$bot, , $plain] = $this->createBot();
        $this->loginBot($plain);

        $bot->update(['is_active' => false]);

        $this->get(route('admin.projets.index'))->assertRedirect(route('login.bot'));
        $this->assertGuest();
    }

    public function test_bot_bypasses_maintenance_only_for_the_panel(): void
    {
        [, , $plain] = $this->createBot();
        app(\App\Services\Maintenance::class)->enable();

        $this->get(route('login.bot'))->assertOk();
        $this->loginBot($plain);
        $this->get(route('admin.projets.index'))->assertOk();
    }
}
