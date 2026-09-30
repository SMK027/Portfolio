<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_a_free_list_of_social_links(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'first_name'   => 'Léo',
            'social_links' => [
                ['name' => 'X', 'url' => 'https://x.com/smk_027'],
                ['name' => 'GitHub', 'url' => 'https://github.com/SMK027'],
                ['name' => 'Vide', 'url' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $links = Profile::current()->socialLinks();
        $this->assertCount(2, $links);
        $this->assertSame('x-twitter', $links[0]['icon']);
        $this->assertSame('github', $links[1]['icon']);

        $this->get('/')->assertSee('https://x.com/smk_027')->assertSee('aria-label="X"', false);
    }

    public function test_social_link_urls_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'social_links' => [['name' => 'Piège', 'url' => 'javascript:alert(1)']],
        ])->assertSessionHasErrors('social_links.0.url');
    }

    public function test_icon_detection(): void
    {
        $this->assertSame('x-twitter', Profile::iconFor('https://twitter.com/smk_027'));
        $this->assertSame('linkedin', Profile::iconFor('https://www.linkedin.com/in/test'));
        $this->assertSame('youtube', Profile::iconFor('https://youtu.be/abc'));
        $this->assertSame('globe', Profile::iconFor('https://leofranz.fr'));
    }
}
