<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_announcements_are_shown_on_every_public_page(): void
    {
        Announcement::create(['title' => 'Je recherche une alternance', 'message' => 'Dès septembre', 'link_url' => 'https://example.com/cv.pdf', 'link_label' => 'Mon CV']);

        foreach (['/', '/formations', '/projets', '/veille', '/contact'] as $url) {
            $this->get($url)->assertOk()->assertSee('Je recherche une alternance')->assertSee('Mon CV');
        }
    }

    public function test_inactive_scheduled_and_expired_announcements_are_hidden(): void
    {
        Announcement::create(['title' => 'Désactivée', 'is_active' => false]);
        Announcement::create(['title' => 'Programmée', 'starts_at' => now()->addDay()]);
        Announcement::create(['title' => 'Expirée', 'ends_at' => now()->subMinute()]);
        Announcement::create(['title' => 'En cours', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $this->get('/')
            ->assertSee('En cours')
            ->assertDontSee('Désactivée')
            ->assertDontSee('Programmée')
            ->assertDontSee('Expirée');
    }

    public function test_announcements_are_ordered_by_position(): void
    {
        Announcement::create(['title' => 'Seconde annonce', 'position' => 2]);
        Announcement::create(['title' => 'Première annonce', 'position' => 1]);

        $this->get('/')->assertSeeInOrder(['Première annonce', 'Seconde annonce']);
    }

    public function test_admin_manages_announcements(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.annonces.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.annonces.create'))->assertOk();

        $this->actingAs($admin)->post(route('admin.annonces.store'), [
            'title'          => 'Recherche de stage',
            'style'          => 'success',
            'link_url'       => 'https://example.com',
            'is_active'      => '1',
            'is_dismissible' => '0',
        ])->assertRedirect(route('admin.annonces.index'));

        $announcement = Announcement::sole();
        $this->assertTrue($announcement->is_active);
        $this->assertFalse($announcement->is_dismissible);
        $this->actingAs($admin)->get(route('admin.annonces.edit', $announcement))->assertOk();

        $this->actingAs($admin)->put(route('admin.annonces.update', $announcement), [
            'title' => 'Recherche de stage (pourvue)', 'style' => 'info', 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($announcement->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.annonces.destroy', $announcement));
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_announcement_input_is_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.annonces.store'), [
            'title'     => '',
            'style'     => 'rouge',
            'link_url'  => 'javascript:alert(1)',
            'starts_at' => '2026-10-10 10:00',
            'ends_at'   => '2026-10-01 10:00',
        ])->assertSessionHasErrors(['title', 'style', 'link_url', 'ends_at']);
    }

    public function test_non_admins_cannot_manage_announcements(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.annonces.index'))->assertForbidden();
        $this->post(route('admin.annonces.store'), ['title' => 'x'])->assertForbidden();
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->post(route('admin.annonces.store'), ['title' => 'x'])->assertRedirect(route('login'));
    }
}
