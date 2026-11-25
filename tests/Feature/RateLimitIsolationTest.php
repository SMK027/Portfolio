<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Chaque route limitée a son propre compteur : l'activité en arrière-plan ne bloque pas un formulaire. */
class RateLimitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_background_requests_do_not_consume_the_contact_form_limit(): void
    {
        $browser = ['User-Agent' => 'Mozilla/5.0 Chrome/130.0'];

        // Quelques minutes de lecture : battements de la mesure d'audience et des succès.
        for ($i = 0; $i < 6; $i++) {
            $this->post('/stats/duree', ['id' => 'x', 'seconds' => 30], $browser);
            $this->postJson('/succes/evenement', ['event' => 'minutes_spent'], $browser);
        }

        $this->assertNotSame(429, $this->post(route('contact.store'), [], $browser)->getStatusCode());
    }

    public function test_each_route_keeps_its_own_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), []);
        }
        $this->post(route('contact.store'), [])->assertStatus(429);
    }
}
