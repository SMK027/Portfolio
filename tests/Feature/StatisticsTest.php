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

    public function test_public_page_views_are_recorded_with_visitor_details(): void
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
        $this->assertSame('127.0.0.1', $view->ip_address);
        $this->assertSame('desktop', $view->device);
        $this->assertSame('Chrome', $view->browser);
        $this->assertSame('Linux', $view->os);
        $this->assertNull($view->country); // IP locale
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

    public function test_new_and_returning_visitors_and_visits_via_cookies(): void
    {
        $first = $this->get('/', self::BROWSER)->assertCookie('pv_vid')->assertCookie('pv_sid');
        $vid = $first->getCookie('pv_vid', false)->getValue();
        $sid = $first->getCookie('pv_sid', false)->getValue();

        // Même visite (cookies renvoyés) : visiteur connu, même identifiant de visite.
        $this->withUnencryptedCookies(['pv_vid' => $vid, 'pv_sid' => $sid])->get('/formations', self::BROWSER);
        // Nouvelle visite plus tard : seul le cookie visiteur subsiste.
        $this->unencryptedCookies = [];
        $this->withUnencryptedCookies(['pv_vid' => $vid])->get('/', self::BROWSER);

        $views = PageView::orderBy('id')->get();
        // 1re visite (2 pages) : nouveau visiteur ; visite suivante : récurrent.
        $this->assertSame([true, true, false], $views->pluck('is_new_visitor')->all());
        $this->assertSame(1, $views->pluck('visitor_id')->unique()->count());
        $this->assertSame(2, $views->pluck('session_id')->unique()->count());

        $stats = app(\App\Services\SiteStatistics::class)->summary(7);
        $this->assertSame(2, $stats['visits']);
        $this->assertSame(1, $stats['newVisits']);
    }

    public function test_device_detection(): void
    {
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1']);
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1']);
        $this->assertSame(['mobile', 'tablet'], PageView::orderBy('id')->pluck('device')->all());
        $this->assertSame('iOS', PageView::first()->os);
    }

    public function test_reading_time_is_recorded_by_beacon(): void
    {
        $html = $this->get('/', self::BROWSER)->getContent();
        preg_match('/data-page-view="([0-9a-f-]{36})"/', $html, $m);
        $this->assertNotEmpty($m);

        $this->post(route('stats.duration'), ['id' => $m[1], 'seconds' => 42])->assertNoContent();
        $this->post(route('stats.duration'), ['id' => $m[1], 'seconds' => 10]); // ne diminue jamais
        $this->post(route('stats.duration'), ['id' => $m[1], 'seconds' => 99999]); // plafonné
        $this->assertSame(1800, PageView::sole()->duration);

        $this->post(route('stats.duration'), ['id' => 'nimporte-quoi', 'seconds' => 5])->assertNoContent();
        $this->assertSame('42 s', \App\Services\SiteStatistics::duration(42));
        $this->assertSame('2 min 05 s', \App\Services\SiteStatistics::duration(125));
    }

    public function test_ips_are_erased_after_three_months_and_hidden_from_bots(): void
    {
        PageView::create(['path' => '/', 'route' => 'home', 'visitor' => str_repeat('a', 16), 'viewed_on' => now()->subMonths(4)->toDateString(), 'ip_address' => '203.0.113.9']);
        PageView::create(['path' => '/', 'route' => 'home', 'visitor' => str_repeat('b', 16), 'viewed_on' => now()->toDateString(), 'ip_address' => '203.0.113.10', 'country' => 'FR']);
        app(\App\Services\SiteStatistics::class)->prune();
        $this->assertSame([null, '203.0.113.10'], PageView::orderBy('id')->pluck('ip_address')->all());

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.statistics'))->assertSee('203.0.113.10')->assertSee('France');

        $bot = User::factory()->create(['global_role' => 'bot', 'permissions' => ['stats.read'], 'is_active' => true]);
        [, $plain] = \App\Models\ServiceToken::issue($bot, 'Test');
        auth()->logout();
        $this->post(route('login.bot'), ['code' => $plain]);
        $this->get(route('admin.statistics'))->assertOk()->assertSee('France')->assertDontSee('203.0.113.10');
    }
}
