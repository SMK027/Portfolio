<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Diagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_live_diagnostics(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.diagnostics'))->assertOk()->assertSee('Diagnostic en temps réel');

        $data = $this->getJson(route('admin.diagnostics.data'))->assertOk()
            ->assertJsonStructure(['checked_at', 'overall', 'groups', 'errors', 'system'])->json();
        $checks = collect($data['groups'])->flatten(1)->keyBy('label');
        $this->assertSame('ok', $checks['Base de données']['status']);
        $this->assertSame('ok', $checks['Cache']['status']);
        $this->assertSame('warning', $checks['Planificateur']['status']); // jamais passé

        // Battement récent du planificateur, puis arrêt depuis 10 minutes
        Cache::forever(Diagnostics::SCHEDULER_HEARTBEAT, now()->subSeconds(30)->timestamp);
        $checks = collect($this->getJson(route('admin.diagnostics.data'))->json('groups'))->flatten(1)->keyBy('label');
        $this->assertSame('ok', $checks['Planificateur']['status']);
        Cache::forever(Diagnostics::SCHEDULER_HEARTBEAT, now()->subMinutes(10)->timestamp);
        $this->assertSame('error', $this->getJson(route('admin.diagnostics.data'))->json('overall'));
    }

    public function test_reserved_to_super_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.diagnostics'))->assertForbidden();
        $this->getJson(route('admin.diagnostics.data'))->assertForbidden();
    }

    /** Production : cache en base, objets non désérialisables (serializable_classes = false). */
    public function test_works_with_the_database_cache_store(): void
    {
        config(['cache.default' => 'database']);
        $this->actingAs(User::factory()->superAdmin()->create());

        // Ancien battement enregistré comme objet date : illisible, sans faire échouer la page
        Cache::forever(Diagnostics::SCHEDULER_HEARTBEAT, now());
        $this->get(route('admin.diagnostics'))->assertOk();

        // Battement réellement enregistré par la tâche planifiée
        $this->artisan('schedule:run')->assertSuccessful();
        $checks = collect($this->getJson(route('admin.diagnostics.data'))->assertOk()->json('groups'))->flatten(1)->keyBy('label');
        $this->assertSame('ok', $checks['Planificateur']['status']);
        $this->assertSame('ok', $checks['Cache']['status']);
    }
}
