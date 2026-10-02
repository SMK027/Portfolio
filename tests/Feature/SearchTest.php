<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Page;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function article(string $title, string $text, bool $published = true): Article
    {
        return Article::create(['title' => $title, 'author_id' => User::factory()->admin()->create()->id,
            'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => $text]]]],
            'published_at' => $published ? now()->subDay() : null]);
    }

    public function test_finds_published_content_with_excerpt(): void
    {
        $this->article('Sécuriser Docker', 'Les conteneurs rootless limitent les risques.');
        $this->article('Brouillon Docker', 'Pas encore publié.', false);
        Skill::create(['name' => 'Docker', 'category' => 'DevOps']);

        $this->get(route('search', ['q' => 'docker']))->assertOk()
            ->assertSee('Sécuriser Docker')->assertSee('Compétence')
            ->assertDontSee('Brouillon Docker');

        $this->get(route('search', ['q' => 'rootless']))->assertSee('Les conteneurs rootless');
        $this->get(route('search', ['q' => 'paragraph']))->assertSee('Aucun résultat'); // clé JSON, pas du texte

        // Les réglages des blocs (alignement d'une citation…) ne sont ni cherchés ni affichés.
        Article::first()->update(['content' => ['blocks' => [['type' => 'quote', 'data' => ['text' => 'Simplicité', 'caption' => 'Vinci', 'alignment' => 'left']]]]]);
        $this->get(route('search', ['q' => 'simplicité']))->assertSee('Simplicité Vinci')->assertDontSee('Vinci left');
        $this->get(route('search', ['q' => 'left']))->assertSee('Aucun résultat');
    }

    public function test_private_pages_are_not_searched_for_visitors(): void
    {
        $this->article('Kubernetes en pratique', 'Texte.');
        Page::where('key', 'veille')->update(['is_public' => false]);
        Page::flushCache();

        $this->get(route('search', ['q' => 'kubernetes']))->assertDontSee('Kubernetes en pratique');
        // Administrateur : la recherche reste publique, la page privée n'est pas fouillée.
        $this->actingAs(User::factory()->admin()->create())->get(route('search', ['q' => 'kubernetes']))->assertDontSee('Kubernetes en pratique');
    }

    public function test_json_endpoint_for_the_palette(): void
    {
        $article = $this->article('Wi-Fi 7', 'Débits multi-gigabit.');

        $this->getJson(route('search', ['q' => 'wi-fi']))->assertOk()
            ->assertJsonPath('results.0.title', 'Wi-Fi 7')
            ->assertJsonPath('results.0.url', route('articles.show', $article));
        $this->getJson(route('search', ['q' => 'w']))->assertJsonCount(0, 'results');
        $this->get('/')->assertSee('searchPalette', false);
    }

    public function test_private_projects_page_hides_projects_and_themes_from_search(): void
    {
        $project = \App\Models\Project::create(['title' => 'Supervision Zabbix', 'published_on' => now(), 'description' => ['blocks' => []]]);
        \App\Models\Theme::create(['name' => 'Zabbix et supervision']);
        $this->get(route('search', ['q' => 'zabbix']))->assertSee('Supervision Zabbix')->assertSee('Zabbix et supervision');

        Page::where('key', 'projets')->update(['is_public' => false]);
        Page::flushCache();

        foreach ([null, User::factory()->admin()->create()] as $user) {
            if ($user) {
                $this->actingAs($user);
            }
            $this->get(route('search', ['q' => 'zabbix']))->assertDontSee('Supervision Zabbix')->assertDontSee('Zabbix et supervision');
            $this->getJson(route('search', ['q' => 'zabbix']))->assertJsonCount(0, 'results');
        }
    }
}
