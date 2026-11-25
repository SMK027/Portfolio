<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackAchievements;
use App\Models\Achievement;
use App\Models\AchievementSettings;
use App\Models\AchievementVisitor;
use App\Models\Page;
use App\Models\User;
use App\Services\Achievements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    protected const BROWSER = ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130.0'];

    protected function visitor(): AchievementVisitor
    {
        return AchievementVisitor::sole();
    }

    /** Requête suivante du même visiteur (cookie du jeton). */
    protected function asVisitor(): static
    {
        return $this->withCredentials()->withCookie(Achievements::COOKIE, $this->visitor()->token);
    }

    public function test_default_achievements_are_installed(): void
    {
        $this->assertGreaterThan(15, Achievement::count());
        $this->assertSame(0, Achievement::whereNotIn('category', array_keys(\App\Achievements\Triggers::CATEGORIES))->count());
        $this->assertSame(0, Achievement::whereNotIn('trigger', array_keys(\App\Achievements\Triggers::ALL))->count());
    }

    public function test_visiting_pages_unlocks_an_achievement_and_shows_a_toast(): void
    {
        Page::query()->update(['status' => Page::PUBLIC]);
        Page::flushCache();

        $this->get('/', self::BROWSER)->assertOk()->assertCookie(Achievements::COOKIE)->assertDontSee('achievement-toasts', false);
        $this->asVisitor()->get('/formations', self::BROWSER)->assertOk()->assertDontSee('achievement-toasts', false);
        $this->asVisitor()->get('/competences', self::BROWSER)->assertOk()
            ->assertSee('id="achievement-toasts"', false)->assertSee('Premiers pas');

        $this->assertSame(3, $this->visitor()->progressOf('sections_visited'));
        $this->assertDatabaseCount('achievement_visitors', 1);
        $this->assertSame(1, Achievement::where('title', 'Premiers pas')->sole()->unlocks()->count());
    }

    public function test_private_pages_and_bots_do_not_count(): void
    {
        Page::where('key', 'formations')->update(['status' => Page::PRIVATE]);
        Page::flushCache();

        $this->get('/formations', self::BROWSER)->assertNotFound();
        $this->get('/', ['User-Agent' => 'Googlebot/2.1']);
        $this->assertDatabaseCount('achievement_visitors', 0);
    }

    public function test_page_lists_achievements_and_hides_secret_ones(): void
    {
        $this->get('/succes', self::BROWSER)->assertOk()
            ->assertSee('Premiers pas')->assertSee('Succès secret')->assertDontSee('Oiseau de nuit')
            // Consulter la page débloque le succès secret « Méta ».
            ->assertSee('id="achievement-toasts"', false);

        $this->asVisitor()->get('/succes', self::BROWSER)->assertOk()->assertSee('Méta')->assertSee('Débloqué le');
    }

    public function test_browser_events_are_restricted_and_announced_in_a_header(): void
    {
        $this->postJson('/succes/evenement', ['event' => 'contact_sent'], self::BROWSER)->assertUnprocessable();

        $response = $this->postJson('/succes/evenement', ['event' => 'palette_opened'], self::BROWSER)->assertOk();
        $toasts = json_decode(base64_decode($response->headers->get(TrackAchievements::HEADER)), true);
        $this->assertSame('Raccourci clavier', $toasts[0]['title']);
    }

    public function test_minutes_cannot_be_inflated(): void
    {
        $this->postJson('/succes/evenement', ['event' => 'minutes_spent'], self::BROWSER)->assertOk();
        $this->asVisitor()->postJson('/succes/evenement', ['event' => 'minutes_spent'], self::BROWSER)->assertOk();
        $this->assertSame(1, $this->visitor()->progressOf('minutes_spent'));

        $this->travel(61)->seconds();
        $this->asVisitor()->postJson('/succes/evenement', ['event' => 'minutes_spent'], self::BROWSER)->assertOk();
        $this->assertSame(2, $this->visitor()->progressOf('minutes_spent'));
    }

    public function test_meta_achievement_counts_other_unlocks(): void
    {
        Achievement::where('trigger', 'achievements_unlocked')->update(['threshold' => 2]);

        $this->postJson('/succes/evenement', ['event' => 'palette_opened'], self::BROWSER);
        $this->asVisitor()->postJson('/succes/evenement', ['event' => 'theme_toggled'], self::BROWSER);

        $this->assertSame(1, Achievement::where('title', 'Collectionneur')->sole()->unlocks()->count());
    }

    public function test_disabled_system_hides_everything(): void
    {
        AchievementSettings::save(['enabled' => false]);

        $this->get('/succes', self::BROWSER)->assertNotFound();
        $this->postJson('/succes/evenement', ['event' => 'palette_opened'], self::BROWSER)->assertNotFound();
        $this->get('/', self::BROWSER)->assertDontSee(route('achievements.index'));
        $this->assertDatabaseCount('achievement_visitors', 0);
    }
}
