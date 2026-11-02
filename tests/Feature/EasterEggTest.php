<?php

namespace Tests\Feature;

use App\Models\EasterEgg;
use App\Models\EasterEggSettings;
use App\Models\User;
use App\Services\EasterEggs;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EasterEggTest extends TestCase
{
    use RefreshDatabase;

    protected const KONAMI = ['arrowup', 'arrowup', 'arrowdown', 'arrowdown', 'arrowleft', 'arrowright', 'arrowleft', 'arrowright', 'b', 'a'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 15:00')); // lundi
        EasterEggSettings::save(['enabled' => true, 'word' => 'licorne', 'click_selector' => '#logo']);
    }

    protected function egg(array $attributes = []): EasterEgg
    {
        return EasterEgg::create($attributes + ['title' => 'Bravo', 'type' => 'text', 'body' => 'Un **secret**.', 'is_active' => true]);
    }

    /** Déclencheur réussi puis lien signé ouvert : accès de session. */
    protected function enter(): void
    {
        $url = $this->postJson(route('easter-eggs.trigger'), ['keys' => self::KONAMI])->assertOk()->json('url');
        $this->get($url)->assertRedirect(route('easter-eggs.show'));
    }

    public function test_page_is_not_reachable_by_url_alone(): void
    {
        $this->get(route('easter-eggs.show'))->assertNotFound();
        $this->get(route('easter-eggs.show').'?signature=faux&expires=9999999999')->assertNotFound();
    }

    public function test_wrong_trigger_is_refused_and_right_ones_give_a_signed_link(): void
    {
        $this->postJson(route('easter-eggs.trigger'), ['keys' => ['a', 'b']])->assertNotFound();
        $this->postJson(route('easter-eggs.trigger'), ['keys' => str_split('licorne')])->assertOk()->assertJsonStructure(['url']);
        $this->postJson(route('easter-eggs.trigger'), ['click' => true])->assertOk();

        EasterEggSettings::save(['enabled' => false]);
        $this->postJson(route('easter-eggs.trigger'), ['keys' => self::KONAMI])->assertNotFound();
    }

    public function test_signed_link_grants_access_then_expires(): void
    {
        $this->egg();
        $this->enter();

        $this->get(route('easter-eggs.show'))->assertOk()->assertSee('<strong>secret</strong>', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertSee('noindex', false);

        $this->travel(31)->minutes();
        $this->get(route('easter-eggs.show'))->assertNotFound();
    }

    public function test_layout_only_exposes_fingerprints_of_the_triggers(): void
    {
        $html = $this->get(route('easter-eggs.show'))->getContent(); // 404, pas de layout public
        $this->assertStringNotContainsString('licorne', $html);

        $triggers = app(EasterEggs::class)->triggers();
        $this->assertSame(hash('sha256', implode(' ', self::KONAMI)), $triggers['sequences'][0]['hash']);
        $this->assertStringNotContainsString('licorne', json_encode($triggers));
    }

    public function test_locked_element_is_revealed_by_its_own_combination(): void
    {
        $egg = $this->egg(['unlock_sequence' => 'p o n e y', 'hint' => 'Un petit cheval']);
        $this->enter();

        $this->get(route('easter-eggs.show'))->assertOk()->assertSee('Un petit cheval')->assertDontSee('<strong>secret</strong>', false);
        $this->postJson(route('easter-eggs.unlock', $egg), ['keys' => ['c', 'h', 'e', 'v', 'a', 'l']])->assertStatus(422);
        $this->postJson(route('easter-eggs.unlock', $egg), ['keys' => ['p', 'o', 'n', 'e', 'y']])->assertOk();

        $this->get(route('easter-eggs.show'))->assertSee('<strong>secret</strong>', false);
    }

    public function test_display_conditions(): void
    {
        $this->egg(['title' => 'Toujours']);
        $this->egg(['title' => 'Plus tard', 'starts_at' => '2026-12-01 00:00']);
        $this->egg(['title' => 'Le mardi', 'weekdays' => [2]]);
        $this->egg(['title' => 'La nuit', 'time_from' => '22:00', 'time_to' => '02:00']);
        $this->egg(['title' => 'Sur mobile', 'device' => 'mobile']);
        $this->egg(['title' => 'Hors de France', 'countries' => ['FR'], 'country_mode' => 'except']);
        $this->egg(['title' => 'En Belgique', 'countries' => ['BE']]);
        $this->egg(['title' => 'Désactivé', 'is_active' => false]);
        $this->enter();

        $this->get(route('easter-eggs.show'), ['CF-IPCountry' => 'FR'])->assertOk()
            ->assertSee('Toujours')
            ->assertDontSee('Plus tard')->assertDontSee('Le mardi')->assertDontSee('La nuit')
            ->assertDontSee('Sur mobile')->assertDontSee('Hors de France')->assertDontSee('En Belgique')->assertDontSee('Désactivé');

        $this->travelTo(CarbonImmutable::parse('2026-11-03 23:30')); // mardi soir
        $this->enter();
        $this->get(route('easter-eggs.show'), ['User-Agent' => 'Mozilla/5.0 (iPhone)', 'CF-IPCountry' => 'BE'])
            ->assertSee('Le mardi')->assertSee('La nuit')->assertSee('Sur mobile')->assertSee('Hors de France')->assertSee('En Belgique');
    }

    public function test_progression_reveals_elements_after_unlocks(): void
    {
        $first = $this->egg(['title' => 'Premier', 'unlock_sequence' => 'u n']);
        $this->egg(['title' => 'Récompense', 'min_unlocked' => 1]);
        $this->enter();

        $this->get(route('easter-eggs.show'))->assertDontSee('Récompense');
        $this->postJson(route('easter-eggs.unlock', $first), ['keys' => ['u', 'n']])->assertOk();
        $this->get(route('easter-eggs.show'))->assertSee('Récompense');
    }

    public function test_images_are_private_and_served_only_once_found(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.easter-eggs.store'), [
            'title' => 'GIF', 'type' => 'image', 'country_mode' => 'only', 'unlock_sequence' => 'chat', 'is_active' => '1',
            'images' => [UploadedFile::fake()->image('chat.gif'), UploadedFile::fake()->image('chien.png')],
        ])->assertRedirect(route('admin.easter-eggs.index'));
        $egg = EasterEgg::sole();
        [$first, $second] = $egg->images->all();
        Storage::disk('local')->assertExists([$first->path, $second->path]);
        $this->assertSame('c h a t', $egg->unlock_sequence);

        auth()->logout();
        $this->get($first->url())->assertNotFound();
        $this->enter();
        $this->get($first->url())->assertNotFound(); // encore verrouillé
        $this->postJson(route('easter-eggs.unlock', $egg), ['keys' => ['c', 'h', 'a', 't']])->assertOk();
        $this->get($first->url())->assertOk();
        $this->get(route('easter-eggs.show'))->assertSee('aria-roledescription="carrousel"', false)->assertSee($second->url(), false);

        // Une image d'un autre élément n'est pas accessible par celui-ci.
        $other = $this->egg(['type' => 'image']);
        $this->get(route('easter-eggs.image', [$other, $first]))->assertNotFound();
    }

    public function test_images_can_be_removed_but_an_image_element_keeps_at_least_one(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
        $egg = $this->egg(['type' => 'image']);
        $image = $egg->images()->create(['path' => UploadedFile::fake()->image('a.png')->store('easter-eggs', 'local')]);
        $fields = ['title' => 'GIF', 'type' => 'image', 'country_mode' => 'only', 'is_active' => '1'];

        $this->put(route('admin.easter-eggs.update', $egg), $fields + ['remove_images' => [$image->id]])->assertSessionHasErrors('images');
        $this->put(route('admin.easter-eggs.update', $egg), $fields + ['remove_images' => [$image->id], 'images' => [UploadedFile::fake()->image('b.png')]])
            ->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing($image->path);
        $this->assertSame(1, $egg->images()->count());

        $path = $egg->images()->value('path');
        $this->delete(route('admin.easter-eggs.destroy', $egg));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_admin_settings_and_permissions(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.easter-eggs.index'))->assertOk()->assertSee('Déclencheurs');
        $this->get(route('easter-eggs.show'))->assertOk()->assertSee('Aperçu administrateur'); // aperçu sans déclencheur

        $this->put(route('admin.easter-eggs.settings'), [
            'enabled' => '1', 'title' => 'Chut', 'access_minutes' => 5, 'sequence' => 'x y z', 'word' => 'deux mots', 'click_count' => 3,
        ])->assertSessionHasErrors('word');

        $this->post(route('admin.easter-eggs.store'), ['title' => 'Pays', 'type' => 'text', 'body' => 'x', 'countries' => 'fr, be', 'country_mode' => 'only', 'weekdays' => [1, 2]])
            ->assertRedirect();
        $this->assertSame(['FR', 'BE'], EasterEgg::sole()->countries);
        $this->assertSame([1, 2], EasterEgg::sole()->weekdays);
    }
}
