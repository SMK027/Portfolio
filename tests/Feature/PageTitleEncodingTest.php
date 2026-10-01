<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Titres d'onglets : caractères spéciaux échappés une seule fois. */
class PageTitleEncodingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_titles_are_escaped_once(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::create(['title' => 'Q&A : l\'API <v2>', 'author_id' => $admin->id, 'content' => ['blocks' => []], 'published_at' => now()->subDay()]);

        $this->actingAs($admin)->get(route('admin.articles.edit', $article))
            ->assertSee('<title>Modifier l&#039;article — ', false)
            ->assertDontSee('&amp;#039;', false);

        $this->get(route('admin.articles.show', $article))
            ->assertSee('<title>Q&amp;A : l&#039;API &lt;v2&gt; — Administration', false)
            ->assertDontSee('&amp;amp;', false);

        $this->get(route('admin.dashboard'))->assertSee('<title>Tableau de bord — ', false);
    }

    public function test_public_and_guest_titles(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::create(['title' => 'Q&A : l\'API', 'author_id' => $admin->id, 'content' => ['blocks' => []], 'published_at' => now()->subDay()]);

        $this->actingAs($admin)->get(route('articles.show', $article))
            ->assertSee('<title>Q&amp;A : l&#039;API — ', false)
            ->assertDontSee('&amp;amp;', false);

        auth()->logout();
        $this->get(route('password.request'))->assertSee('<title>Mot de passe oublié — ', false);
        $this->get(route('login'))->assertSee('Connexion — ', false);
    }
}
