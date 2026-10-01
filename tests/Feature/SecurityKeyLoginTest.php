<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WebAuthn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SecurityKeyLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_offers_security_key_login(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Se connecter avec une clé de sécurité');
    }

    public function test_options_require_user_verification_and_do_not_leak_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->securityKeys()->create(['name' => 'YubiKey', 'credential_id' => 'abc', 'public_key' => 'pem']);
        $contributor = User::factory()->create(['global_role' => 'user']);
        $contributor->securityKeys()->create(['name' => 'Clé', 'credential_id' => 'def', 'public_key' => 'pem']);

        $this->postJson(route('login.key.options'))->assertOk()
            ->assertJsonPath('publicKey.userVerification', 'required')
            ->assertJsonMissingPath('publicKey.allowCredentials');
        $this->postJson(route('login.key.options'), ['email' => $admin->email])->assertJsonPath('publicKey.allowCredentials.0.id', 'abc');
        // Contributeur ou e-mail inconnu : même réponse qu'en l'absence d'e-mail.
        $this->postJson(route('login.key.options'), ['email' => $contributor->email])->assertJsonMissingPath('publicKey.allowCredentials');
        $this->postJson(route('login.key.options'), ['email' => 'inconnu@example.com'])->assertJsonMissingPath('publicKey.allowCredentials');
    }

    public function test_verified_admin_key_logs_in_directly(): void
    {
        $admin = User::factory()->admin()->create();
        $this->mock(WebAuthn::class, fn ($mock) => $mock->shouldReceive('verifyLogin')->once()->andReturn($admin));

        $this->postJson(route('login.key'), ['credential' => ['id' => 'abc']])->assertOk()->assertJsonStructure(['redirect']);
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.security_key_login']);
    }

    public function test_refused_key_is_logged_and_rate_limited(): void
    {
        $this->mock(WebAuthn::class, fn ($mock) => $mock->shouldReceive('verifyLogin')->andReturn(null));

        foreach (range(1, 10) as $i) {
            $this->postJson(route('login.key'), ['credential' => ['id' => 'x']])->assertStatus(422);
        }
        $this->postJson(route('login.key'), ['credential' => ['id' => 'x']])->assertStatus(429);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.failed']);
    }

    public function test_real_verification_rejects_non_admins_and_unknown_keys(): void
    {
        $contributor = User::factory()->create(['global_role' => 'user']);
        $contributor->securityKeys()->create(['name' => 'Clé', 'credential_id' => 'def', 'public_key' => 'pem']);
        $webauthn = app(WebAuthn::class);

        $this->postJson(route('login.key.options')); // défi en session
        $this->assertNull(app(WebAuthn::class)->verifyLogin(['id' => 'def', 'response' => []]));
        $this->postJson(route('login.key'), ['credential' => ['id' => 'inconnue', 'response' => []]])->assertStatus(422);
        $this->assertGuest();
        $this->assertFalse($contributor->canUsePasswordlessLogin());
    }
}
