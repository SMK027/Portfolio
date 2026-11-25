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

    public function test_visitor_is_kept_by_the_session_when_the_cookie_is_lost(): void
    {
        $this->postJson('/succes/evenement', ['event' => 'palette_opened'], self::BROWSER)->assertOk();

        // Même session, mais sans le cookie dédié.
        $this->get('/succes', self::BROWSER)->assertOk()->assertSee('Raccourci clavier')->assertSee('Débloqué le');
        $this->assertDatabaseCount('achievement_visitors', 1);
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

    public function test_admin_manages_achievements_and_sees_stats(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.succes.index'))->assertOk()->assertSee('Premiers pas');

        $this->actingAs($admin)->post(route('admin.succes.store'), [
            'title' => 'Touche-à-tout', 'description' => 'Ouvrir la palette deux fois.', 'icon' => 'squares',
            'category' => 'interaction', 'trigger' => 'palette_opened', 'threshold' => 2, 'is_active' => '1',
        ])->assertRedirect(route('admin.succes.index'));
        $achievement = Achievement::where('title', 'Touche-à-tout')->sole();

        $this->actingAs($admin)->post(route('admin.succes.store'), ['title' => 'X', 'icon' => 'trophy', 'category' => 'interaction', 'trigger' => 'inconnu', 'threshold' => 1])
            ->assertSessionHasErrors('trigger');

        $this->actingAs($admin)->put(route('admin.succes.update', $achievement), [
            'title' => 'Touche-à-tout', 'icon' => 'squares', 'category' => 'secret', 'trigger' => 'palette_opened', 'threshold' => 3, 'is_secret' => '1',
        ])->assertRedirect();
        $this->assertTrue($achievement->fresh()->is_secret);
        $this->assertFalse($achievement->fresh()->is_active);

        $this->actingAs($admin)->put(route('admin.succes.settings'), ['enabled' => '0', 'title' => 'Trophées'])->assertRedirect();
        $this->assertFalse(AchievementSettings::enabled());

        $this->actingAs($admin)->delete(route('admin.succes.destroy', $achievement));
        $this->assertModelMissing($achievement);
    }

    public function test_admin_resets_progress(): void
    {
        $this->postJson('/succes/evenement', ['event' => 'palette_opened'], self::BROWSER);
        $this->assertDatabaseCount('achievement_unlocks', 1);

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.succes.reset'))->assertRedirect();
        $this->assertDatabaseCount('achievement_visitors', 0);
        $this->assertDatabaseCount('achievement_unlocks', 0);
    }

    public function test_sandbox_achievements_are_installed(): void
    {
        $this->assertSame(5, Achievement::where('category', 'sandbox')->count());
        $this->assertSame(1, Achievement::where('title', 'Premier défi')->count());
    }

    public function test_opening_the_web_terminal_unlocks_an_achievement(): void
    {
        config(['services.sandbox' => ['url' => 'http://sandbox-agent:8080', 'token' => str_repeat('t', 40), 'ssh_host' => 'example.com', 'ssh_port' => 2222, 'ws_url' => null]]);
        Page::where('key', 'machine-ssh')->update(['status' => Page::PUBLIC]);
        Page::flushCache();
        app(\App\Services\Sandbox::class)->saveSettings(['public' => true]);

        $response = $this->postJson(route('sandbox.ticket'), [], self::BROWSER)->assertOk();
        $toasts = json_decode(base64_decode($response->headers->get(TrackAchievements::HEADER)), true);
        $this->assertSame('Bienvenue à bord', $toasts[0]['title']);
    }

    public function test_sandbox_challenges_are_validated_on_the_site(): void
    {
        Page::where('key', 'machine-ssh')->update(['status' => Page::PUBLIC]);
        Page::flushCache();

        // Page affichée même machine fermée : les réponses se valident à tout moment.
        $this->get(route('sandbox.show'), self::BROWSER)->assertOk()->assertSee('Défis Linux')->assertSee('Visiteurs uniques');

        $this->postJson(route('sandbox.challenge'), ['number' => 1, 'answer' => 'manchot'], self::BROWSER)->assertUnprocessable();
        $this->assertDatabaseCount('achievement_unlocks', 0);

        $answers = [1 => 'pingouin', 2 => 'ALPINE42', 3 => 'chmod', 4 => ' diamant ', 5 => '37'];
        foreach ($answers as $number => $answer) {
            $this->withCredentials()->withCookie(Achievements::COOKIE, AchievementVisitor::first()?->token ?? '')
                ->postJson(route('sandbox.challenge'), ['number' => $number, 'answer' => $answer], self::BROWSER)
                ->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, "Défi {$number} validé"));
        }
        // Valider deux fois le même défi ne compte qu'une fois.
        $this->asVisitor()->postJson(route('sandbox.challenge'), ['number' => 1, 'answer' => 'pingouin'], self::BROWSER)->assertOk();

        $this->assertSame(5, $this->visitor()->progressOf('sandbox_challenges'));
        $this->assertSame(1, Achievement::where('title', 'Maître du shell')->sole()->unlocks()->count());
    }

    public function test_challenge_answers_match_the_machine_build_script(): void
    {
        $script = file_get_contents(base_path('sandbox/ssh/defis/build.sh'));
        foreach (['pingouin' => 1, 'ALPINE42' => 2, 'chmod' => 3, 'diamant' => 4] as $answer => $number) {
            $this->assertStringContainsString("answer {$number} {$answer}", $script);
            $this->assertTrue(\App\Achievements\SandboxChallenges::check($number, $answer));
        }
        // Défi 5 : nombre d'IP distinctes « 203.0.113.(i * 7 % 37) » pour i < 500.
        $this->assertStringContainsString('$((i * 7 % 37))', $script);
        $this->assertSame(37, count(array_unique(array_map(fn ($i) => $i * 7 % 37, range(0, 499)))));
        $this->assertTrue(\App\Achievements\SandboxChallenges::check(5, '37'));
    }
}
