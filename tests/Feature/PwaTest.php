<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_describes_the_installable_app(): void
    {
        Profile::current()->update(['first_name' => 'Léo', 'last_name' => 'Franz', 'headline' => 'Développeur']);

        $response = $this->get(route('pwa.manifest'))->assertOk()->assertHeader('Content-Type', 'application/manifest+json');

        $response->assertJsonPath('name', 'Léo Franz')
            ->assertJsonPath('short_name', 'Léo')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('scope', '/');
        $this->assertSame(['192x192', '512x512', '512x512'], array_column($response->json('icons'), 'sizes'));
        $this->assertSame('maskable', $response->json('icons.2.purpose'));
    }

    public function test_icons_are_generated_as_png(): void
    {
        Profile::current()->update(['first_name' => 'Léo', 'last_name' => 'Franz']);

        foreach ([192, 512] as $size) {
            $png = $this->get(route('pwa.icon', ['size' => $size]))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
            $this->assertSame([$size, $size], array_slice(getimagesizefromstring($png), 0, 2));
        }
        $this->get(route('pwa.icon', ['size' => 64]))->assertNotFound();
    }

    public function test_offline_page_and_service_worker_registration(): void
    {
        $this->get(route('pwa.offline'))->assertOk()->assertSee('Vous êtes hors connexion')->assertSee('data-offline-list', false);
        $this->assertFileExists(public_path('sw.js'));

        $this->get(route('home'))
            ->assertSee('<link rel="manifest" href="'.route('pwa.manifest').'">', false)
            ->assertSee('data-service-worker="'.asset('sw.js').'"', false);

        // Connecté : pas de service worker (aucune page privée en cache).
        $this->actingAs(User::factory()->admin()->create())->get(route('home'))->assertDontSee('data-service-worker=', false);
    }
}
