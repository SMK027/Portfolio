<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function maintenance(): Maintenance
    {
        return app(Maintenance::class);
    }

    public function test_site_is_not_in_maintenance_by_default(): void
    {
        $this->assertFalse($this->maintenance()->isActive());
        $this->get('/')->assertOk()->assertDontSee('Site en maintenance');
    }

    public function test_visitors_see_the_maintenance_page_with_status_200(): void
    {
        $this->maintenance()->enable(now()->addHours(2), 'Mise à jour de mes projets');

        foreach (['/', '/formations', '/projets', '/veille', '/contact'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Site en maintenance')
                ->assertSee('Mise à jour de mes projets')
                ->assertSee('Retour prévu le')
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_reason_and_end_date_are_optional(): void
    {
        $this->maintenance()->enable();

        $this->get('/')->assertOk()->assertSee('temporairement indisponible')->assertDontSee('Retour prévu le');
    }

    public function test_visitors_cannot_submit_forms_or_download_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.projets.store'), [
            'title' => 'P', 'published_on' => '2026-01-01', 'description' => 'D',
            'files' => [UploadedFile::fake()->image('a.jpg')],
        ]);
        $fileUrl = Project::sole()->files->first()->url();
        auth()->logout();

        $this->maintenance()->enable();

        $this->get($fileUrl)->assertOk()->assertSee('Site en maintenance');
        $this->post('/contact', ['first_name' => 'A'])->assertOk()->assertSee('Site en maintenance');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_admins_keep_browsing_and_editing(): void
    {
        $admin = User::factory()->admin()->create();
        $this->maintenance()->enable();

        $this->actingAs($admin)->get('/')->assertOk()->assertDontSee('Site en maintenance')->assertSee('Maintenance active');
        $this->actingAs($admin)->get('/projets')->assertOk()->assertDontSee('Site en maintenance');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Maintenance active');
        $this->actingAs($admin)->post(route('admin.competences.store'), ['name' => 'PHP'])->assertRedirect();
        $this->assertDatabaseHas('skills', ['name' => 'PHP']);
    }

    public function test_non_admin_users_see_the_maintenance_page(): void
    {
        $this->maintenance()->enable();

        $this->actingAs(User::factory()->create())->get('/')->assertSee('Site en maintenance');
    }

    public function test_login_stays_reachable_during_maintenance(): void
    {
        $this->maintenance()->enable();
        $admin = User::factory()->admin()->create();

        $this->get('/login')->assertOk()->assertDontSee('Site en maintenance');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_maintenance_ends_automatically(): void
    {
        $this->maintenance()->enable(now()->addHour());
        $this->get('/')->assertSee('Site en maintenance');

        $this->travel(61)->minutes();

        $this->get('/')->assertOk()->assertDontSee('Site en maintenance');
        $this->assertFalse(\App\Models\Setting::get(Maintenance::ENABLED));
    }

    public function test_admin_enables_and_disables_maintenance(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.maintenance.edit'))->assertOk();

        $endsAt = now()->addDay()->startOfMinute();
        $this->actingAs($admin)->put(route('admin.maintenance.update'), [
            'enabled' => '1',
            'ends_at' => $endsAt->format('Y-m-d\TH:i'),
            'reason'  => 'Nouveau design',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->maintenance()->isActive());
        $this->assertTrue($endsAt->equalTo($this->maintenance()->endsAt()));
        $this->assertSame('Nouveau design', $this->maintenance()->reason());

        $this->actingAs($admin)->put(route('admin.maintenance.update'), ['enabled' => '0']);
        $this->assertFalse($this->maintenance()->isActive());
    }

    public function test_end_date_must_be_in_the_future(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.maintenance.update'), [
            'enabled' => '1',
            'ends_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('ends_at');

        $this->assertFalse($this->maintenance()->isActive());
    }

    public function test_only_admins_can_toggle_maintenance(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.maintenance.update'), ['enabled' => '1'])->assertForbidden();
        $this->assertFalse($this->maintenance()->isActive());
    }
}
