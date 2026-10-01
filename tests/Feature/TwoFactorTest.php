<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SecurityKey;
use App\Models\ServiceToken;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected const PASSWORD = 'Mot-de-passe-2026!';

    protected function admin(): User
    {
        return User::factory()->admin()->create(['password' => self::PASSWORD]);
    }

    /** Active l'application d'authentification comme le ferait l'utilisateur. */
    protected function enableTotp(User $user): string
    {
        $this->actingAs($user)->post(route('two-factor.totp.start'))->assertRedirect();
        $secret = \Illuminate\Support\Facades\Crypt::decryptString(session('two_factor.pending_secret'));
        $this->put(route('two-factor.totp.confirm'), ['code' => Totp::code($secret, intdiv(time(), 30))])
            ->assertSessionHas('recovery_codes');
        auth()->logout();
        $this->flushSession();

        return $secret;
    }

    protected function login(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
    }

    public function test_totp_matches_rfc_6238_vectors(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('94287082', Totp::code($secret, intdiv(59, 30), 8));
        $this->assertSame('07081804', Totp::code($secret, intdiv(1111111109, 30), 8));
        $this->assertSame('14050471', Totp::code($secret, intdiv(1111111111, 30), 8));
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
        $this->assertNotNull(Totp::verify($secret, Totp::code($secret, intdiv(1111111109, 30)), null, 1111111109));
        $this->assertNull(Totp::verify($secret, '000000', null, 1111111109));
    }

    public function test_enabling_the_app_requires_a_valid_code_and_shows_recovery_codes(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->post(route('two-factor.totp.start'));
        $this->get(route('profile.edit'))->assertSee('<svg', false)->assertSee('Clé de configuration');

        $this->put(route('two-factor.totp.confirm'), ['code' => '000000'])->assertSessionHasErrorsIn('twoFactor', 'code');
        $this->assertFalse($user->fresh()->hasTotp());

        $this->enableTotp($user);
        $this->assertTrue($user->fresh()->hasTotp());
        $this->assertSame(8, $user->fresh()->recoveryCodesLeft());
        $this->assertNotNull(AuditLog::where('action', 'two_factor.enabled')->first());
    }

    public function test_login_requires_the_second_factor_and_no_session_before(): void
    {
        $user = $this->admin();
        $secret = $this->enableTotp($user);

        $this->login($user)->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->assertNull(AuditLog::where('action', 'auth.login')->first());
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->post(route('two-factor.challenge'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        // Le pas de temps de l'activation est déjà consommé : on prend le suivant.
        $code = Totp::code($secret, intdiv(time(), 30) + 1);
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect(route('admin.dashboard')); // page visée avant la connexion
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(AuditLog::where('action', 'auth.two_factor')->first());

        // Un code ne sert qu'une fois.
        auth()->logout();
        $this->login($user);
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_codes_work_once(): void
    {
        $user = $this->admin();
        $this->enableTotp($user);
        $this->actingAs($user)->post(route('two-factor.recovery-codes'), ['password' => self::PASSWORD]);
        $code = session('recovery_codes')[0];
        auth()->logout();

        $this->login($user);
        $this->post(route('two-factor.challenge'), ['recovery_code' => strtoupper($code)])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(7, $user->fresh()->recoveryCodesLeft());

        auth()->logout();
        $this->login($user);
        $this->post(route('two-factor.challenge'), ['recovery_code' => $code])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_attempts_are_limited(): void
    {
        $user = $this->admin();
        $this->enableTotp($user);
        $this->login($user);

        foreach (range(1, 5) as $i) {
            $this->post(route('two-factor.challenge'), ['code' => '000000']);
        }
        $this->post(route('two-factor.challenge'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => trans('auth.throttle', ['seconds' => \Illuminate\Support\Facades\RateLimiter::availableIn('two-factor:'.$user->id)])]);
        $this->assertSame(6, AuditLog::where('action', 'auth.two_factor_failed')->count() + 1);
    }

    public function test_challenge_expires_and_requires_a_pending_login(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));

        $user = $this->admin();
        $this->enableTotp($user);
        $this->login($user);
        $this->travel(11)->minutes();
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_security_key_alone_triggers_the_challenge(): void
    {
        $user = $this->admin();
        $user->securityKeys()->create(['name' => 'YubiKey', 'credential_id' => 'abc', 'public_key' => 'pem']);

        $this->login($user)->assertRedirect(route('two-factor.challenge'));
        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Utiliser ma clé de sécurité')->assertDontSee('Code de l\'application');
        $this->postJson(route('two-factor.key-options'))->assertOk()->assertJsonPath('publicKey.allowCredentials.0.id', 'abc');
        $this->postJson(route('two-factor.key'), ['credential' => ['id' => 'abc', 'response' => []]])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_registration_options_exclude_existing_keys(): void
    {
        $user = $this->admin();
        $user->securityKeys()->create(['name' => 'YubiKey', 'credential_id' => 'abc', 'public_key' => 'pem']);

        $this->actingAs($user)->postJson(route('two-factor.keys.options'))->assertOk()
            ->assertJsonPath('publicKey.rp.id', 'localhost')
            ->assertJsonPath('publicKey.excludeCredentials.0.id', 'abc');
        $this->postJson(route('two-factor.keys.store'), ['name' => 'Autre', 'credential' => ['response' => []]])->assertStatus(422);
    }

    public function test_disabling_requires_password_and_removing_last_factor_clears_codes(): void
    {
        $user = $this->admin();
        $this->enableTotp($user);
        $key = $user->securityKeys()->create(['name' => 'YubiKey', 'credential_id' => 'abc', 'public_key' => 'pem']);

        $this->actingAs($user)->delete(route('two-factor.totp.disable'), ['password' => 'faux'])->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->assertTrue($user->fresh()->hasTotp());

        $this->delete(route('two-factor.totp.disable'), ['password' => self::PASSWORD]);
        $this->assertFalse($user->fresh()->hasTotp());
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->assertSame(8, $user->fresh()->recoveryCodesLeft());

        $this->delete(route('two-factor.keys.destroy', $key), ['password' => self::PASSWORD]);
        $this->assertFalse($user->fresh()->hasTwoFactor());
        $this->assertSame(0, $user->fresh()->recoveryCodesLeft());
    }

    public function test_cannot_delete_someone_elses_key(): void
    {
        $owner = $this->admin();
        $key = $owner->securityKeys()->create(['name' => 'YubiKey', 'credential_id' => 'abc', 'public_key' => 'pem']);

        $this->actingAs($this->admin())->delete(route('two-factor.keys.destroy', $key), ['password' => self::PASSWORD])->assertNotFound();
        $this->assertModelExists($key);
    }

    public function test_bots_cannot_use_two_factor(): void
    {
        $bot = User::factory()->create(['global_role' => 'bot', 'permissions' => [], 'is_active' => true]);
        [, $plain] = ServiceToken::issue($bot, 'Test');
        $this->post(route('login.bot'), ['code' => $plain]);
        $this->assertAuthenticatedAs($bot);

        $this->post(route('two-factor.totp.start'))->assertForbidden();
        $this->postJson(route('two-factor.keys.options'))->assertForbidden();
        $this->assertFalse($bot->hasTwoFactor());
    }

    public function test_super_admin_resets_two_factor(): void
    {
        $user = $this->admin();
        $this->enableTotp($user);
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($this->admin())->delete(route('admin.utilisateurs.two-factor.reset', $user))->assertForbidden();
        $this->actingAs($super)->get(route('admin.utilisateurs.edit', $user))->assertSee('Réinitialiser la double authentification');
        $this->delete(route('admin.utilisateurs.two-factor.reset', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->hasTwoFactor());
        $this->assertNotNull(AuditLog::where('action', 'two_factor.reset')->first());

        auth()->logout();
        $this->login($user)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_secrets_never_appear_in_audit_log(): void
    {
        $user = $this->admin();
        $secret = $this->enableTotp($user);

        $this->assertStringNotContainsString($secret, AuditLog::all()->toJson());
        $this->assertNotSame($secret, \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('two_factor_secret'));
    }
}
