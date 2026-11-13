<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaitingRoomTicket;
use App\Services\WaitingRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingRoomTest extends TestCase
{
    use RefreshDatabase;

    protected function room(): WaitingRoom
    {
        return app(WaitingRoom::class);
    }

    /** Visite avec un éventuel ticket ; renvoie la réponse et le ticket reçu. */
    protected function visit(?string $token = null, string $url = '/')
    {
        $this->flushHeaders();
        $this->defaultCookies = [];
        if ($token) {
            $this->withCookie(WaitingRoom::COOKIE, $token);
        }

        return $this->get($url);
    }

    protected function tokenOf($response): ?string
    {
        return $response->getCookie(WaitingRoom::COOKIE)?->getValue();
    }

    public function test_disabled_by_default_without_tracking(): void
    {
        $this->visit()->assertOk()->assertDontSee('Votre position')->assertCookieMissing(WaitingRoom::COOKIE);
        $this->assertSame(0, WaitingRoomTicket::count());
    }

    public function test_auto_mode_queues_visitors_beyond_capacity_and_admits_them_in_order(): void
    {
        $this->room()->update(['mode' => 'auto', 'capacity' => 2]);

        $first = $this->visit();
        $first->assertOk()->assertDontSee('Votre position');
        $this->visit()->assertDontSee('Votre position');

        $third = $this->visit();
        $third->assertOk()->assertSee('Votre position')->assertHeader('Cache-Control', 'no-store, private');
        $fourth = $this->visit();
        $fourth->assertSee('Votre position');

        $this->assertTrue($this->room()->isQueueing());
        $this->assertSame(1, $this->room()->position(WaitingRoomTicket::where('token', $this->tokenOf($third))->first()));
        $this->assertSame(2, $this->room()->position(WaitingRoomTicket::where('token', $this->tokenOf($fourth))->first()));

        // Les visiteurs admis continuent de naviguer.
        $this->visit($this->tokenOf($first), '/projets')->assertOk()->assertDontSee('Votre position');

        // Le premier visiteur devient inactif : sa place revient au premier en attente.
        $this->travel(4)->minutes();
        $this->visit($this->tokenOf($third))->assertSee('Votre position'); // toujours plein
        $this->travel(2)->minutes();
        $this->withCookie(WaitingRoom::COOKIE, $this->tokenOf($third));
        $this->withCredentials()->getJson(route('waiting-room.status'))->assertJson(['admitted' => true]);
    }

    public function test_status_endpoint_reports_position(): void
    {
        $this->room()->update(['mode' => 'manual', 'capacity' => 1]);
        WaitingRoomTicket::create(['token' => str_repeat('a', 40), 'admitted_at' => now(), 'last_seen_at' => now()]);

        $token = $this->tokenOf($this->visit()->assertSee('Votre position'));

        $this->withCookie(WaitingRoom::COOKIE, $token);
        $this->withCredentials()->getJson(route('waiting-room.status'))->assertJson(['admitted' => false, 'position' => 1]);
    }

    public function test_manual_mode_lets_newcomers_in_when_there_is_room(): void
    {
        $this->room()->update(['mode' => 'manual', 'capacity' => 10]);

        $this->visit()->assertOk()->assertDontSee('Votre position');
        $this->assertSame(1, WaitingRoomTicket::admitted()->count());
    }

    public function test_manual_mode_falls_back_to_auto_at_end_date(): void
    {
        $this->room()->update(['mode' => 'manual', 'ends_at' => now()->addHour()->toIso8601String()]);
        $this->travel(2)->hours();

        $this->assertSame('auto', $this->room()->mode());
    }

    public function test_logged_in_users_and_bots_bypass_the_queue(): void
    {
        $this->room()->update(['mode' => 'manual', 'capacity' => 1]);
        WaitingRoomTicket::create(['token' => str_repeat('a', 40), 'admitted_at' => now(), 'last_seen_at' => now()]);

        $this->actingAs(User::factory()->admin()->create())->get('/')->assertDontSee('Votre position');
        auth()->logout();

        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/')->assertSee('Votre position');
        $this->assertSame(0, WaitingRoomTicket::waiting()->count());
    }

    public function test_admin_can_configure_and_release_the_queue(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.waiting-room.edit'))->assertOk()->assertSee('Désactivée');

        $this->actingAs($admin)->put(route('admin.waiting-room.update'), [
            'mode' => 'manual', 'capacity' => 1, 'session_minutes' => 5, 'message' => 'Patience !',
        ])->assertSessionHasNoErrors();
        $this->assertSame('manual', $this->room()->mode());

        auth()->logout();
        WaitingRoomTicket::create(['token' => str_repeat('a', 40), 'admitted_at' => now(), 'last_seen_at' => now()]);
        $this->visit()->assertSee('Patience !');
        $this->assertSame(1, $this->room()->waitingCount());

        $this->actingAs($admin)->post(route('admin.waiting-room.admit'))->assertSessionHas('success');
        $this->assertSame(0, $this->room()->waitingCount());

        $this->actingAs($admin)->put(route('admin.waiting-room.update'), ['mode' => 'off', 'capacity' => 1, 'session_minutes' => 5]);
        $this->assertSame(0, WaitingRoomTicket::count());
    }
}
