<?php

namespace Tests\Feature;

use App\Models\SandboxManager;
use App\Models\User;
use App\Services\Sandbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SandboxManagerTest extends TestCase
{
    use RefreshDatabase;

    protected const ED25519 = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIEStHPrPRjm5ZjE/OtY2ApCtMH0HcfytJ1uR0to9j5jK test-gestion';

    protected const RSA_1024 = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAAAgQCyyAS8c5Zzc3xwgoaG+ypLlvSDzqhX6XYE2z2lFkgdqARz7j6HLo1VqGE7vXME6NtTNtQV+Oh9Ew1XysbHWzcUsgCkfINQBCBEkwnj9Z91685RXsz9ELE2aTEfscszFgQS6M+imHitGEqL/0lWe/H5/P7KJo5O/T7kf0ouCvrFdQ== smk@LeoHP';

    protected const RSA_3072 = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQCytkLtyG7M7kcou/k4h7NeaC+qYIYdyVW3I4nEwcRYBCYwcnpcFNKYlvEm2IWqivitkVn4m8CqQH4CEun+jSFLsLOGyUUxDjdkYzL9FEks0nt7Ohtwr5Odq5SPCdUdoJXI2M8XLFIiYz235O3o7hikNZuNUeZr8YHZ2BJs0dXqdReVLK1RSrw97VBd5b6g5t6/qZRSEvhJ6HaMYIGAtMMP5McMZM+luWSe9jWSCJXi7WgV3ULXdjkV3AzAgssESQgwkF9qy1T+Vfp/A2YLyBt/z6izhP2ytIagptmnimOxvYw/jrb5kMXSa5IgqKWe+rFLliv+4NYdQoDFZUDYMfgHn4qXbI58MjuuuqRVsNJ2e9X2myqZ6sXQuulQIfG4MOXcWK2MmibgXxMEvl+UzgHoIxrtfm2vBzBcYFYjGCcBfLW0LhfLMf0aOq3epE8F6kPednBJmOrDI7WBZqdpyTKzeM5HJjxalxdFZOHZemdVhUw6Azjh4DSCxg0NNWuP/Ss= smk@LeoHP';

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sandbox' => ['url' => 'http://sandbox-agent:8080', 'token' => str_repeat('t', 40), 'ssh_host' => 'example.com', 'ssh_port' => 2222, 'ssh_manager_port' => null, 'ws_url' => null]]);
        Http::fake(['sandbox-agent:8080/*' => Http::response(['ok' => true, 'last_error' => null, 'ssh' => ['port' => 2222, 'manager_port' => 2224]])]);
        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function confirmed(?User $user = null): static
    {
        return $this->actingAs($user ?? $this->admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_creating_a_root_account_requires_password_confirmation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.sandbox.managers.store'), ['manager_username' => 'leo', 'public_key' => self::ED25519])
            ->assertRedirect(route('password.confirm'));
        $this->assertDatabaseCount('sandbox_managers', 0);
    }

    public function test_super_admin_creates_a_root_account_and_manages_keys(): void
    {
        $this->confirmed()->post(route('admin.sandbox.managers.store'), ['manager_username' => 'leo', 'public_key' => self::ED25519, 'key_label' => 'Portable'])
            ->assertSessionHas('success');

        $manager = SandboxManager::sole();
        $this->assertSame('adm-leo', $manager->username);
        $this->assertSame('Portable', $manager->public_keys[0]['label']);
        $this->assertStringStartsWith('SHA256:', $manager->public_keys[0]['fingerprint']);

        $this->confirmed()->post(route('admin.sandbox.managers.keys.store'), ['public_key' => self::ED25519])->assertSessionHasErrors('public_key'); // doublon
        $this->confirmed()->post(route('admin.sandbox.managers.keys.store'), ['public_key' => self::RSA_1024])->assertSessionHasErrors('public_key'); // trop faible
        $this->confirmed()->post(route('admin.sandbox.managers.keys.store'), ['public_key' => 'ssh-dss AAAAB3NzaC1kc3MAAACBAP'])->assertSessionHasErrors('public_key');
        $this->confirmed()->post(route('admin.sandbox.managers.keys.store'), ['public_key' => self::RSA_3072])->assertSessionHas('success');
        $this->assertCount(2, $manager->fresh()->public_keys);

        $this->actingAs($this->admin)->delete(route('admin.sandbox.managers.keys.destroy'), ['fingerprint' => $manager->public_keys[0]['fingerprint']])->assertSessionHas('success');
        $this->assertSame('ssh-rsa', $manager->fresh()->public_keys[0]['type']);

        $this->actingAs($this->admin)->get(route('admin.sandbox.show'))->assertOk()->assertSee('ssh -p 2224 adm-leo@example.com');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/state') && $r['managers'][0]['username'] === 'adm-leo');
    }

    public function test_only_active_super_admins_get_root_access(): void
    {
        $demoted = User::factory()->superAdmin()->create();
        $inactive = User::factory()->superAdmin()->create(['is_active' => false]);
        $key = fn () => [SandboxManager::parseKey(self::ED25519)];
        SandboxManager::create(['user_id' => $this->admin->id, 'username' => 'adm-actif', 'public_keys' => $key()]);
        SandboxManager::create(['user_id' => $demoted->id, 'username' => 'adm-retrograde', 'public_keys' => $key()]);
        SandboxManager::create(['user_id' => $inactive->id, 'username' => 'adm-inactif', 'public_keys' => $key()]);
        $demoted->forceFill(['global_role' => 'admin'])->save();

        $managers = app(Sandbox::class)->desiredState()['managers'];

        $this->assertSame(['adm-actif'], array_column($managers, 'username'));
        $this->assertStringStartsWith('ssh-ed25519 ', $managers[0]['keys'][0]);
    }

    public function test_any_super_admin_can_revoke_and_admins_cannot_manage(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $manager = SandboxManager::create(['user_id' => $owner->id, 'username' => 'adm-autre', 'public_keys' => [SandboxManager::parseKey(self::ED25519)]]);

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.sandbox.managers.destroy', $manager))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('admin.sandbox.managers.destroy', $manager))->assertSessionHas('success');
        $this->assertDatabaseCount('sandbox_managers', 0);
    }

    public function test_visitor_accounts_cannot_use_the_manager_prefix(): void
    {
        $this->actingAs($this->admin)->post(route('admin.sandbox.accounts.store'), ['username' => 'adm-pirate'])->assertSessionHasErrors('username');
    }

    public function test_root_logins_are_audited_and_notified(): void
    {
        app(\App\Services\DiscordNotifier::class)->save('https://discord.com/api/webhooks/1/abc', ['sandbox']);
        SandboxManager::create(['user_id' => $this->admin->id, 'username' => 'adm-leo', 'public_keys' => [SandboxManager::parseKey(self::ED25519)]]);

        app(Sandbox::class)->recordConnections([
            ['at' => '2026-11-03T10:00:00Z', 'ip' => '203.0.113.9', 'channel' => 'gestion', 'username' => 'adm-leo', 'result' => 'authentifiée'],
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'sandbox.manager_login']);
        $this->assertDatabaseHas('sandbox_connections', ['channel' => 'gestion', 'username' => 'adm-leo']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'discord.com') && str_contains($r['embeds'][0]['title'], 'connexion root de adm-leo'));
    }
}
