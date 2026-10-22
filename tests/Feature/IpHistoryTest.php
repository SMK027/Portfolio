<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_trace_the_history_of_an_ip(): void
    {
        $view = fn (string $path, string $ip) => PageView::create(['path' => $path, 'route' => 'home', 'visitor' => str_repeat('a', 16), 'viewed_on' => now()->toDateString(), 'ip_address' => $ip, 'country' => 'FR', 'city' => 'Lyon', 'browser' => 'Firefox']);
        $view('/projets/page-tracee', '203.0.113.7');
        $view('/articles/page-autre-ip', '198.51.100.1');
        ContactMessage::create(['first_name' => 'Ada', 'last_name' => 'L.', 'email' => 'ada@example.com', 'subject' => 'Proposition de stage', 'message' => 'Bonjour', 'ip_address' => '203.0.113.7', 'consented_at' => now()]);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('admin.ip-history', ['ip' => '203.0.113.7']))->assertOk()
            ->assertSee('/projets/page-tracee')->assertDontSee('/articles/page-autre-ip')
            ->assertSee('Proposition de stage')->assertSee('Lyon');

        $this->assertSame(['ip' => '203.0.113.7'], AuditLog::where('action', 'ip_history.viewed')->first()->meta);

        $this->actingAs($admin)->get(route('admin.ip-history', ['ip' => 'pas-une-ip']))->assertOk()->assertSee('pas une adresse IP valide');
    }

    public function test_only_super_admins_can_trace_an_ip(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.ip-history', ['ip' => '203.0.113.7']))->assertForbidden();
    }
}
