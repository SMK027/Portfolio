<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IpBan;
use App\Models\User;
use App\Services\LoginBan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class IpBanTest extends TestCase
{
    use RefreshDatabase;

    protected function failLogin(string $ip = '203.0.113.7'): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/login', ['email' => 'inconnu@example.com', 'password' => 'mauvais']);
    }

    public function test_three_failures_in_fifteen_minutes_ban_the_ip_for_45_minutes(): void
    {
        Log::shouldReceive('channel')->with('null')->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(fn ($message) => str_contains($message, 'IP bannie : 203.0.113.7'));

        $this->failLogin()->assertSessionHasErrors('email');
        $this->failLogin()->assertSessionHasErrors('email');
        $this->assertSame(0, IpBan::count());

        $this->failLogin();
        $ban = IpBan::sole();
        $this->assertSame('203.0.113.7', $ban->ip_address);
        $this->assertSame(3, $ban->attempts);
        $this->assertEqualsWithDelta(now()->addMinutes(45)->getTimestamp(), $ban->banned_until->getTimestamp(), 5);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.ip_banned', 'subject_label' => '203.0.113.7', 'ip_address' => '203.0.113.7']);

        // Banni de tout le site, API comprise ; les autres adresses ne sont pas concernées
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
        $this->get('/')->assertForbidden()->assertSee('Accès temporairement bloqué')->assertHeader('Retry-After');
        $this->get('/login')->assertForbidden();
        $this->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, 'bloquée'));
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->get('/')->assertOk();

        // Levé automatiquement au bout de 45 minutes
        $this->travel(46)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->get('/')->assertOk();
    }

    public function test_failures_older_than_fifteen_minutes_do_not_count(): void
    {
        $this->failLogin();
        $this->failLogin();
        $this->travel(16)->minutes();
        $this->failLogin();
        $this->failLogin();
        $this->assertSame(0, IpBan::count());

        $this->failLogin();
        $this->assertSame(1, IpBan::count());
    }

    public function test_successful_login_resets_the_counter(): void
    {
        $admin = User::factory()->admin()->create();
        $this->failLogin();
        $this->failLogin();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/logout');

        $this->failLogin();
        $this->assertSame(0, IpBan::count());
    }

    public function test_bot_and_security_key_failures_count(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
        $this->post(route('login.bot'), ['code' => 'pfs_faux']);
        $this->postJson(route('login.key'), ['credential' => []])->assertStatus(422);
        $this->post(route('login.bot'), ['code' => 'pfs_faux']);

        $this->assertTrue(IpBan::active()->where('ip_address', '203.0.113.9')->exists());
        $this->assertStringContainsString('code d\'application', IpBan::sole()->reason);
    }

    public function test_whitelisted_addresses_are_never_banned_nor_blocked(): void
    {
        config(['auth.login_ban.whitelist' => ['203.0.113.7', '192.168.1.0/24']]);

        foreach (range(1, 4) as $i) {
            $this->failLogin('203.0.113.7');
            $this->failLogin('192.168.1.42');
        }
        $this->assertSame(0, IpBan::count());

        // Un bannissement antérieur à l'ajout dans la liste blanche ne bloque plus
        IpBan::create(['ip_address' => '192.168.1.50', 'banned_until' => now()->addHour()]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])->get('/')->assertOk();

        // Hors liste : banni normalement
        foreach (range(1, 3) as $i) {
            $this->failLogin('198.51.100.9');
        }
        $this->assertTrue(IpBan::active()->where('ip_address', '198.51.100.9')->exists());

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.ip-bans.index'))->assertSee('192.168.1.0/24');
    }

    public function test_disabled_ban_never_blocks(): void
    {
        config(['auth.login_ban.enabled' => false]);
        foreach (range(1, 4) as $i) {
            $this->failLogin();
        }
        $this->assertSame(0, IpBan::count());
    }

    public function test_super_admin_lists_edits_and_lifts_bans(): void
    {
        $ban = IpBan::create(['ip_address' => '203.0.113.7', 'attempts' => 3, 'reason' => 'test', 'banned_until' => now()->addMinutes(45)]);
        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super);

        $this->get(route('admin.ip-bans.index'))->assertOk()->assertSee('203.0.113.7');
        $this->get(route('admin.ip-bans.edit', $ban))->assertOk();

        // Prolongation
        $this->put(route('admin.ip-bans.update', $ban), ['permanent' => 0, 'banned_until' => now()->addDays(2)->format('Y-m-d\TH:i'), 'reason' => 'Attaque'])
            ->assertRedirect(route('admin.ip-bans.index'));
        $this->assertTrue($ban->fresh()->banned_until->isAfter(now()->addDay()));
        $this->assertSame('Attaque', $ban->fresh()->reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ip_ban.updated', 'user_id' => $super->id]);

        // Sans date de fin
        $this->put(route('admin.ip-bans.update', $ban), ['permanent' => 1, 'reason' => 'Attaque']);
        $this->assertNull($ban->fresh()->banned_until);
        $this->assertTrue($ban->fresh()->isActive());

        // Date passée refusée
        $this->put(route('admin.ip-bans.update', $ban), ['permanent' => 0, 'banned_until' => now()->subHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('banned_until');

        // Levée
        $this->delete(route('admin.ip-bans.destroy', $ban))->assertRedirect(route('admin.ip-bans.index'));
        $ban->refresh();
        $this->assertFalse($ban->isActive());
        $this->assertSame($super->id, $ban->lifted_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ip_ban.lifted', 'user_id' => $super->id]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->get('/')->assertOk();

        // Un bannissement terminé ne se modifie plus
        $this->get(route('admin.ip-bans.edit', $ban))->assertNotFound();
    }

    public function test_only_super_admins_manage_bans(): void
    {
        $ban = IpBan::create(['ip_address' => '203.0.113.7', 'banned_until' => now()->addHour()]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.ip-bans.index'))->assertForbidden();
        $this->delete(route('admin.ip-bans.destroy', $ban))->assertForbidden();
        $this->assertTrue($ban->fresh()->isActive());
    }

    public function test_command_lists_and_lifts_bans(): void
    {
        IpBan::create(['ip_address' => '203.0.113.7', 'banned_until' => now()->addHour()]);

        $this->artisan('portfolio:ip-ban')->expectsOutputToContain('203.0.113.7')->assertSuccessful();
        $this->artisan('portfolio:ip-ban', ['ip' => '203.0.113.7', '--lift' => true])->assertSuccessful();
        $this->assertSame(0, IpBan::active()->count());
        $this->assertTrue(AuditLog::where('action', 'ip_ban.lifted')->exists());
    }

    public function test_old_bans_are_pruned(): void
    {
        IpBan::create(['ip_address' => '203.0.113.1', 'banned_until' => now()->subMonths(4)]);
        IpBan::create(['ip_address' => '203.0.113.2', 'banned_until' => now()->subMonth()]);
        IpBan::create(['ip_address' => '203.0.113.3', 'banned_until' => null]);

        $this->assertSame(1, app(LoginBan::class)->prune());
        $this->assertSame(['203.0.113.2', '203.0.113.3'], IpBan::orderBy('ip_address')->pluck('ip_address')->all());
    }
}
