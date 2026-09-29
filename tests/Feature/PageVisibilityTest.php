<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public static function publicRoutes(): array
    {
        return [
            'accueil'        => ['home', '/'],
            'formations'     => ['formations', '/formations'],
            'diplomes'       => ['diplomes', '/diplomes'],
            'certifications' => ['certifications', '/certifications'],
            'competences'    => ['competences', '/competences'],
            'projets'        => ['projets', '/projets'],
            'veille'         => ['veille', '/veille'],
            'contact'        => ['contact', '/contact'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_public_pages_are_accessible(string $key, string $url): void
    {
        $this->get($url)->assertOk();
    }

    #[DataProvider('publicRoutes')]
    public function test_private_pages_are_hidden_from_guests_but_visible_to_admins(string $key, string $url): void
    {
        Page::where('key', $key)->update(['is_public' => false]);
        Page::flushCache();

        $response = $this->get($url);
        $key === 'home' ? $response->assertRedirect() : $response->assertNotFound();

        Page::flushCache();
        $this->actingAs(User::factory()->admin()->create())
            ->get($url)
            ->assertOk();
    }

    public function test_private_pages_are_hidden_from_non_admin_users(): void
    {
        Page::where('key', 'projets')->update(['is_public' => false]);

        $this->actingAs(User::factory()->create())->get('/projets')->assertNotFound();
    }

    public function test_menu_hides_private_pages_from_guests_only(): void
    {
        Page::where('key', 'veille')->update(['is_public' => false, 'title' => 'Ma veille secrète']);

        $this->get('/formations')->assertDontSee('Ma veille secrète');

        Page::flushCache();
        $this->actingAs(User::factory()->admin()->create())
            ->get('/formations')
            ->assertSee('Ma veille secrète')
            ->assertSee('Page privée', false);
    }

    public function test_private_home_redirects_guests_to_first_public_page(): void
    {
        Page::where('key', 'home')->update(['is_public' => false]);

        $this->get('/')->assertRedirect(route('formations'));
    }

    public function test_everything_private_returns_404(): void
    {
        Page::query()->update(['is_public' => false]);

        $this->get('/')->assertNotFound();
    }

    public function test_admin_can_change_page_visibility_and_titles(): void
    {
        $admin = User::factory()->admin()->create();
        $pages = Page::all()->mapWithKeys(fn (Page $page) => [$page->id => [
            'title'     => $page->key === 'contact' ? 'Me joindre' : $page->title,
            'intro'     => $page->intro,
            'position'  => $page->position,
            'is_public' => $page->key === 'contact' ? 0 : 1,
        ]])->all();

        $this->actingAs($admin)->put(route('admin.pages.update'), ['pages' => $pages])->assertSessionHasNoErrors();

        $contact = Page::where('key', 'contact')->first();
        $this->assertFalse($contact->is_public);
        $this->assertSame('Me joindre', $contact->title);
    }
}
