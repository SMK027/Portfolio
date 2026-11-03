<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SandboxAccount;
use App\Models\User;
use App\Services\Sandbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SandboxTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sandbox' => [
            'url' => 'http://sandbox-agent:8080', 'token' => str_repeat('t', 40), 'ssh_host' => 'example.com', 'ssh_port' => 2222, 'ws_url' => null,
        ], 'app.url' => 'https://example.com']);
        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function fakeAgent(): void
    {
        Http::fake([
            'sandbox-agent:8080/state' => Http::response(['ok' => true, 'last_error' => null]),
            'sandbox-agent:8080/status' => Http::response([
                'container' => ['running' => true, 'status' => 'running', 'cpu_percent' => 1.5, 'memory_bytes' => 10485760, 'memory_limit_bytes' => 134217728],
                'public' => true, 'internet' => false, 'accounts' => ['guest'],
                'quota' => ['used_kb' => 1024, 'limit_kb' => 524288, 'exceeded' => false],
                'sessions' => [['pid' => 12, 'seconds' => 90, 'username' => 'guest', 'tty' => 'pts/0']],
                'connections' => [['at' => '2026-11-03T10:00:00Z', 'ip' => '203.0.113.5', 'channel' => 'ssh', 'result' => 'ouverte']],
                'last_error' => null,
            ]),
            'sandbox-agent:8080/backups' => fn (HttpRequest $r) => $r->method() === 'POST'
                ? Http::response(['backup' => ['id' => 'abcdef0123456789', 'label' => 'Test', 'automatic' => false, 'size' => 10, 'created_at' => '2026-11-03T09:00:00Z']])
                : Http::response(['backups' => [['id' => 'abcdef0123456789', 'label' => 'Avant démo', 'automatic' => false, 'size' => 2048, 'created_at' => '2026-11-03T09:00:00Z']]]),
            'sandbox-agent:8080/*' => Http::response(['ok' => true]),
        ]);
    }

    public function test_admin_page_shows_agent_status_and_backups(): void
    {
        $this->fakeAgent();

        $this->actingAs($this->admin)->get(route('admin.sandbox.show'))->assertOk()
            ->assertSee('En marche')->assertSee('203.0.113.5')->assertSee('Avant démo')->assertSee('ssh -p 2222 guest@example.com');
    }

    public function test_ssh_address_comes_from_the_agent_unless_overridden(): void
    {
        config(['services.sandbox.ssh_port' => null, 'services.sandbox.ssh_host' => null]);
        Http::fake(['sandbox-agent:8080/state' => Http::response(['ok' => true, 'last_error' => null, 'ssh' => ['port' => 2223, 'host' => 'ssh.example.org']])]);
        $sandbox = app(Sandbox::class);

        $this->assertSame('ssh -p 2222 guest@example.com', $sandbox->sshCommand()); // rien de connu : valeurs par défaut
        $sandbox->sync();
        $this->assertSame('ssh -p 2223 guest@ssh.example.org', $sandbox->sshCommand());

        config(['services.sandbox.ssh_port' => 22, 'services.sandbox.ssh_host' => 'forced.example.net']);
        $this->assertSame('ssh guest@forced.example.net', $sandbox->sshCommand());
    }

    public function test_agent_failures_feed_the_ip_ban_and_bans_are_pushed(): void
    {
        config(['auth.login_ban.enabled' => true, 'auth.login_ban.max_attempts' => 3, 'auth.login_ban.whitelist' => '']);
        Http::fake([
            'sandbox-agent:8080/auth-failures/drain' => Http::response(['failures' => [
                ['ip' => '203.0.113.66', 'channel' => 'ssh', 'username' => 'root'],
                ['ip' => '203.0.113.66', 'channel' => 'ssh', 'username' => 'admin'],
                ['ip' => '203.0.113.66', 'channel' => 'web', 'username' => 'guest'],
                ['ip' => 'pas-une-ip', 'channel' => 'ssh'],
                ['ip' => '172.28.0.1', 'channel' => 'ssh'], ['ip' => '172.28.0.1', 'channel' => 'ssh'], ['ip' => '172.28.0.1', 'channel' => 'ssh'],
            ]]),
            'sandbox-agent:8080/state' => Http::response(['ok' => true, 'last_error' => null]),
        ]);
        $sandbox = app(Sandbox::class);

        $this->assertSame(7, $sandbox->processAuthFailures());
        $this->assertTrue(\App\Models\IpBan::active()->where('ip_address', '203.0.113.66')->exists());
        $this->assertFalse(\App\Models\IpBan::where('ip_address', '172.28.0.1')->exists()); // passerelle Docker : jamais bannie

        $sandbox->sync();
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/state') && $r['banned_ips'] === ['203.0.113.66']);
    }

    public function test_only_super_admins_manage_the_machine(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.sandbox.show'))->assertForbidden();
    }

    public function test_accounts_are_pushed_as_sha512_hashes(): void
    {
        $this->fakeAgent();

        $this->actingAs($this->admin)->post(route('admin.sandbox.accounts.store'), ['username' => 'guest', 'password' => 'Invite-2026', 'is_guest' => '1'])
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sandbox.accounts.store'), ['username' => 'recruteur', 'expires_at' => now()->addWeek()->toDateString()])
            ->assertSessionHas('success');

        $guest = SandboxAccount::where('username', 'guest')->sole();
        $this->assertTrue(hash_equals($guest->password_hash, crypt('Invite-2026', $guest->password_hash)));
        $this->assertSame('Invite-2026', $guest->guestPassword());
        $this->assertNull(SandboxAccount::where('username', 'recruteur')->sole()->password_encrypted);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/state')
            && $r->hasHeader('Authorization', 'Bearer '.str_repeat('t', 40))
            && collect($r['accounts'])->pluck('username')->sort()->values()->all() === ['guest', 'recruteur']
            && str_starts_with($r['accounts'][0]['hash'], '$6$')
            && ! str_contains($r->body(), 'Invite-2026'));
    }

    public function test_reserved_and_invalid_usernames_are_refused(): void
    {
        foreach (['root', 'Bob', 'a', 'nobody', 'x y'] as $username) {
            $this->actingAs($this->admin)->post(route('admin.sandbox.accounts.store'), ['username' => $username])->assertSessionHasErrors('username');
        }
    }

    public function test_expired_and_disabled_accounts_are_sent_disabled(): void
    {
        $account = new SandboxAccount(['username' => 'ancien', 'expires_at' => now()->subDay()]);
        $account->setPassword('motdepasse');
        $account->save();

        $this->assertSame([['username' => 'ancien', 'hash' => $account->password_hash, 'enabled' => false]], app(Sandbox::class)->desiredState()['accounts']);
    }

    public function test_settings_toggle_public_access_and_internet(): void
    {
        $this->fakeAgent();

        $this->actingAs($this->admin)->put(route('admin.sandbox.settings'), ['public' => '1', 'internet' => '1', 'keep' => 5])->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/state') && $r['public'] === true && $r['internet'] === true);
        $this->assertTrue(app(Sandbox::class)->isOpen());
    }

    public function test_backup_actions_are_forwarded_and_audited(): void
    {
        $this->fakeAgent();

        $this->actingAs($this->admin)->post(route('admin.sandbox.backups.store'), ['label' => 'Test'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sandbox.backups.restore', 'abcdef0123456789'))->assertSessionHas('success');
        $this->actingAs($this->admin)->delete(route('admin.sandbox.backups.destroy', 'abcdef0123456789'))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.sandbox.reset'), ['confirm' => 'non'])->assertSessionHasErrors('confirm');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/backups/abcdef0123456789/restore'));
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/backups/abcdef0123456789'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'sandbox.backup_restored']);
    }

    public function test_unreachable_agent_keeps_the_change_and_warns(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));

        $this->actingAs($this->admin)->put(route('admin.sandbox.settings'), ['public' => '1', 'keep' => 7])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'injoignable'));
        $this->assertTrue(app(Sandbox::class)->settings()['public']);
        $this->actingAs($this->admin)->get(route('admin.sandbox.show'))->assertOk()->assertSee('injoignable');
    }

    public function test_public_page_and_signed_ticket(): void
    {
        Page::where('key', 'machine-ssh')->update(['is_public' => true]);
        Page::flushCache();
        $guest = new SandboxAccount(['username' => 'guest', 'is_guest' => true]);
        $guest->setPassword('Invite-2026');
        $guest->save();

        $this->get(route('sandbox.show'))->assertOk()->assertSee('actuellement fermée');
        $this->post(route('sandbox.ticket'))->assertStatus(503);

        app(Sandbox::class)->saveSettings(['public' => true]);
        $this->get(route('sandbox.show'))->assertOk()->assertSee('ssh -p 2222 guest@example.com')->assertSee('Invite-2026');

        $url = $this->post(route('sandbox.ticket'))->assertOk()->json('url');
        $this->assertStringStartsWith('wss://example.com/ssh-ws?ticket=', $url);
        [$payload, $signature] = explode('.', substr($url, strpos($url, '=') + 1));
        $key = hash('sha256', 'terminal|'.str_repeat('t', 40), true);
        $this->assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '='), $signature);
        $this->assertGreaterThan(time(), json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['exp']);
    }

    public function test_private_page_hides_the_machine(): void
    {
        $this->get(route('sandbox.show'))->assertNotFound();
        $this->post(route('sandbox.ticket'))->assertNotFound();
    }
}
