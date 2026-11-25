<?php

namespace Tests\Feature;

use App\Models\Supervisor;
use App\Models\User;
use App\Services\ThrottleSupervision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThrottleSupervisionTest extends TestCase
{
    use RefreshDatabase;

    protected function supervisor(array $permissions = [ThrottleSupervision::OPERATION], string $username = 'chef'): Supervisor
    {
        $supervisor = new Supervisor(['username' => $username, 'user_id' => User::factory()->admin()->create()->id, 'permissions' => $permissions, 'is_active' => true]);
        $supervisor->setPin('1234');
        $supervisor->save();

        return $supervisor;
    }

    /** Épuise la limite du formulaire de contact (5 / 10 min). */
    protected function exhaustContactLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), []);
        }
    }

    public function test_plain_429_without_any_supervisor(): void
    {
        $this->exhaustContactLimit();
        $this->post(route('contact.store'), [])->assertStatus(429)->assertDontSee('Identifiant superviseur');
    }

    public function test_supervisor_unblocks_and_replays_the_request_once(): void
    {
        $this->supervisor();
        $this->exhaustContactLimit();

        $this->post(route('contact.store'), ['name' => 'Ada', 'email' => 'ada@example.com'])
            ->assertStatus(429)->assertSee('Identifiant superviseur')->assertHeader('Retry-After');

        $replay = $this->post(route('throttle-supervision.store'), ['username' => 'chef', 'pin' => '1234'])
            ->assertOk()->assertSee('Demande débloquée')->assertSee('value="Ada"', false);
        preg_match('/name="'.ThrottleSupervision::BYPASS_FIELD.'" value="([^"]+)"/', $replay->getContent(), $m);

        // Rejeu : passe la limite (la validation du formulaire répond, et non le 429).
        $fields = ['name' => 'Ada', 'email' => 'ada@example.com', ThrottleSupervision::BYPASS_FIELD => $m[1]];
        $this->assertNotSame(429, $this->post(route('contact.store'), $fields)->getStatusCode());
        // Jeton à usage unique, et jamais valable dans une autre session.
        $this->post(route('contact.store'), $fields)->assertStatus(429);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supervision.used']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supervision.rejected']);
    }

    public function test_wrong_pin_or_unauthorised_supervisor_is_refused(): void
    {
        $this->supervisor(['contact.skip_verification']);
        $this->supervisor(username: 'adjoint');
        $this->exhaustContactLimit();
        $this->post(route('contact.store'), [])->assertStatus(429);

        $this->post(route('throttle-supervision.store'), ['username' => 'adjoint', 'pin' => '0000'])
            ->assertRedirect(route('throttle-supervision.show'))->assertSessionHasErrors('pin');
        $this->post(route('throttle-supervision.store'), ['username' => 'chef', 'pin' => '1234'])
            ->assertRedirect(route('throttle-supervision.show'))->assertSessionHasErrors('pin');
        $this->get(route('throttle-supervision.show'))->assertOk()->assertSee('Identifiant superviseur');
    }

    public function test_json_and_authentication_limits_are_never_offered(): void
    {
        $this->supervisor();
        for ($i = 0; $i < 31; $i++) {
            $response = $this->postJson('/succes/evenement', ['event' => 'palette_opened']);
        }
        $response->assertStatus(429);
        $this->assertNull(session(ThrottleSupervision::PENDING_KEY));
    }

    public function test_token_is_bound_to_the_visitor_session(): void
    {
        $this->supervisor();
        $this->exhaustContactLimit();
        $this->post(route('contact.store'), [])->assertStatus(429);
        $replay = $this->post(route('throttle-supervision.store'), ['username' => 'chef', 'pin' => '1234']);
        preg_match('/name="'.ThrottleSupervision::BYPASS_FIELD.'" value="([^"]+)"/', $replay->getContent(), $m);

        $this->flushSession();
        $this->post(route('contact.store'), [ThrottleSupervision::BYPASS_FIELD => $m[1]])->assertStatus(429);
    }
}
