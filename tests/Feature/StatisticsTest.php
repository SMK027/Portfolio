<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected const BROWSER = ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130.0'];

    public function test_public_page_views_are_recorded_without_personal_data(): void
    {
        $this->get('/', self::BROWSER + ['Referer' => 'https://www.linkedin.com/feed/'])->assertOk();
        $this->get('/', self::BROWSER)->assertOk();

        $this->assertSame(2, PageView::count());
        $view = PageView::first();
        $this->assertSame('/', $view->path);
        $this->assertSame('home', $view->route);
        $this->assertSame('linkedin.com', $view->referrer_host);
        $this->assertSame(16, strlen($view->visitor));
        $this->assertSame(1, PageView::distinct('visitor')->count('visitor'));
        $this->assertDatabaseMissing('page_views', ['visitor' => '127.0.0.1']);
    }

    public function test_bots_logged_in_users_dnt_and_admin_pages_are_ignored(): void
    {
        $this->get('/', ['User-Agent' => 'Googlebot/2.1']);
        $this->get('/', self::BROWSER + ['DNT' => '1']);
        $this->get('/', self::BROWSER + ['Sec-GPC' => '1']);
        $this->get('/', self::BROWSER + ['Sec-Purpose' => 'prefetch']);
        $this->get('/une-page-absente', self::BROWSER);
        $this->actingAs(User::factory()->admin()->create())->get('/', self::BROWSER);
        $this->get(route('admin.dashboard'), self::BROWSER);

        $this->assertSame(0, PageView::count());
    }

    public function test_statistics_page_and_permissions(): void
    {
        PageView::create(['path' => '/', 'route' => 'home', 'visitor' => str_repeat('a', 16), 'viewed_on' => now()->toDateString(), 'referrer_host' => 'github.com']);
        PageView::create(['path' => '/', 'route' => 'home', 'visitor' => str_repeat('b', 16), 'viewed_on' => now()->subDays(40)->toDateString()]);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.statistics'))
            ->assertOk()->assertSee('Accueil')->assertSee('github.com');
        $this->get(route('admin.dashboard'))->assertSee('Visites des 7 derniers jours');

        $this->actingAs(User::factory()->create(['global_role' => 'user']))->get(route('admin.statistics'))->assertForbidden();
    }

    public function test_old_data_is_pruned(): void
    {
        PageView::create(['path' => '/', 'route' => 'home', 'visitor' => str_repeat('a', 16), 'viewed_on' => now()->subMonths(14)->toDateString()]);
        PageView::create(['path' => '/', 'route' => 'home', 'visitor' => str_repeat('b', 16), 'viewed_on' => now()->subMonths(2)->toDateString()]);

        $this->assertSame(1, app(\App\Services\SiteStatistics::class)->prune());
        $this->assertSame(1, PageView::count());
    }
}
