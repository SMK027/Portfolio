<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaletteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<string, mixed>> */
    protected function commands(User $user): array
    {
        $html = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();
        preg_match('#<script type="application/json" id="admin-commands">(.*?)</script>#s', $html, $match);

        return json_decode($match[1] ?? 'null', true);
    }

    public function test_palette_lists_the_menu_links_of_the_user(): void
    {
        $labels = collect($this->commands(User::factory()->superAdmin()->create()))->pluck('label');

        $this->assertContains('Tableau de bord', $labels);
        $this->assertContains('Recherche de stage', $labels);
        $this->assertContains('Journal d\'activité', $labels);
        $this->assertContains('Mon compte', $labels);

        // Un administrateur ne voit pas les pages réservées aux super-administrateurs
        $labels = collect($this->commands(User::factory()->admin()->create()))->pluck('label');
        $this->assertContains('Messages', $labels);
        $this->assertNotContains('Journal d\'activité', $labels);
    }
}
