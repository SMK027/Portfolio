<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ServiceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->superAdmin()->create();
    }

    protected function createAccount(array $permissions = ['articles.read']): User
    {
        $this->actingAs($this->super)->post(route('admin.service-accounts.store'), [
            'type' => 'service', 'name' => 'Import ancien site', 'description' => 'Migration', 'is_active' => '1', 'permissions' => $permissions,
        ])->assertSessionHasNoErrors();

        return User::where('global_role', 'service')->latest('id')->first();
    }

    public function test_only_super_admins_manage_service_accounts(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.service-accounts.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.service-accounts.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.service-accounts.index'))->assertRedirect(route('login'));

        $this->actingAs($this->super)->get(route('admin.service-accounts.index'))->assertOk()->assertSee('Documentation de');
    }

    public function test_super_admin_creates_an_account_with_permissions(): void
    {
        $this->actingAs($this->super)->post(route('admin.service-accounts.store'), [
            'name' => 'Invalide', 'permissions' => ['articles.read', 'inexistante'],
        ])->assertSessionHasErrors('permissions.1');
        $this->assertSame(0, User::where('global_role', 'service')->count());

        $account = $this->createAccount(['articles.write', 'articles.read']);
        $this->assertTrue($account->isService());
        $this->assertSame(['articles.read', 'articles.write'], $account->permissions);
        $this->assertStringEndsWith('@service.invalid', $account->email);

        $this->actingAs($this->super)->put(route('admin.service-accounts.update', $account), [
            'name' => 'Import ancien site', 'is_active' => '1', 'permissions' => ['articles.read'],
        ]);
        $log = AuditLog::where('action', 'user.updated')->latest('id')->first();
        $this->assertSame(['articles.read'], $log->changes['permissions']['new']);
    }

    public function test_code_is_shown_once_and_only_its_hash_is_stored(): void
    {
        $account = $this->createAccount();

        $response = $this->actingAs($this->super)->post(route('admin.service-accounts.tokens.store', $account), ['name' => 'Serveur CI']);
        $plain = session('service_token')['plain'];
        $this->assertStringStartsWith('pfs_', $plain);

        $token = ServiceToken::sole();
        $this->assertSame('Serveur CI', $token->name);
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertStringNotContainsString($plain, json_encode(AuditLog::all()));

        // Affiché sur la page qui suit la création, puis plus jamais.
        $this->actingAs($this->super)->get(route('admin.service-accounts.show', $account))->assertSee($plain);
        $this->actingAs($this->super)->get(route('admin.service-accounts.show', $account))->assertDontSee($plain)->assertSee($token->token_prefix);
    }

    public function test_service_accounts_cannot_log_into_the_panel_and_are_hidden_from_user_lists(): void
    {
        $account = $this->createAccount();
        $account->forceFill(['password' => 'Connu-2026'])->save();
        auth()->logout();

        $this->post('/login', ['email' => $account->email, 'password' => 'Connu-2026'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->super)->get(route('admin.utilisateurs.index'))->assertDontSee('Import ancien site');
        $this->actingAs($this->super)->get(route('admin.utilisateurs.edit', $account))->assertNotFound();
        $this->actingAs($this->super)->get(route('admin.articles.create'))->assertDontSee('Import ancien site');
    }

    public function test_disabling_reenabling_and_deleting_a_code(): void
    {
        $account = $this->createAccount();
        [$token, $plain] = ServiceToken::issue($account, 'Test');
        $headers = ['Authorization' => 'Bearer '.$plain];

        $this->getJson('/api/v1/me', $headers)->assertOk();

        $this->actingAs($this->super)->patch(route('admin.service-accounts.tokens.toggle', [$account, $token]));
        $this->assertTrue($token->fresh()->isDisabled());
        $this->assertNotNull(AuditLog::where('action', 'service_token.updated')->first());
        auth()->logout();
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();

        $this->actingAs($this->super)->patch(route('admin.service-accounts.tokens.toggle', [$account, $token]));
        $this->assertFalse($token->fresh()->isDisabled());
        auth()->logout();
        $this->getJson('/api/v1/me', $headers)->assertOk();

        $this->actingAs($this->super)->delete(route('admin.service-accounts.tokens.destroy', [$account, $token]));
        $this->assertNull($token->fresh());
        auth()->logout();
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }
}
