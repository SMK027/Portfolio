<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function deindex(): void
    {
        Setting::set(Setting::INDEXABLE, false);
    }

    public function test_site_is_indexable_by_default(): void
    {
        $this->assertTrue(Setting::siteIsIndexable());

        $response = $this->get('/');
        $response->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->assertStringNotContainsString('noindex', $response->getContent());

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("Disallow:\n", false)
            ->assertDontSee('Disallow: /storage/');
    }

    public function test_deindexed_site_sends_noindex_everywhere(): void
    {
        $this->deindex();

        foreach (['/', '/formations', '/projets', '/veille', '/contact'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }

        // Les pages restent explorables pour que la consigne soit lue ; seules les images publiques sont bloquées.
        $this->get('/robots.txt')
            ->assertSee('Disallow: /storage/')
            ->assertDontSee("Disallow: /\n", false);
    }

    public function test_deindexed_site_also_covers_served_files_and_404(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.projets.store'), [
            'title' => 'P', 'published_on' => '2026-01-01', 'description' => 'D',
            'files' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
        ]);
        $url = \App\Models\ProjectFile::sole()->url();
        auth()->logout();

        $this->get($url)->assertHeaderMissing('X-Robots-Tag');

        $this->deindex();
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/inexistant')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_admin_and_login_are_never_indexed(): void
    {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_admin_toggles_indexing(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.seo.edit'))->assertOk()->assertSee('Le site peut être indexé');

        $this->actingAs($admin)->put(route('admin.seo.update'), ['indexable' => '0'])->assertSessionHas('success');
        $this->assertFalse(Setting::siteIsIndexable());
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Le site est désindexé');
        $this->actingAs($admin)->get('/')->assertSee('Site non indexé');

        $this->actingAs($admin)->put(route('admin.seo.update'), ['indexable' => '1']);
        $this->assertTrue(Setting::siteIsIndexable());
    }

    public function test_only_admins_can_change_indexing(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.seo.update'), ['indexable' => '0'])->assertForbidden();
        $this->assertTrue(Setting::siteIsIndexable());
    }
}
