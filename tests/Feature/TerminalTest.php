<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Page;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalTest extends TestCase
{
    use RefreshDatabase;

    protected function terminalData(): array
    {
        $html = $this->get(route('terminal'))->assertOk()->assertSee('noindex', false)->getContent();
        preg_match('#<script type="application/json" data-terminal-data>(.*?)</script>#s', $html, $match);

        return json_decode($match[1], true);
    }

    public function test_terminal_exposes_public_content(): void
    {
        Profile::current()->update(['first_name' => 'Léo', 'last_name' => 'Franz', 'headline' => 'Développeur', 'email' => 'leo@example.com']);
        Skill::create(['name' => 'Laravel', 'category' => 'Back-end', 'level' => 4]);
        Project::create(['title' => 'Portfolio', 'published_on' => '2026-01-01', 'description' => ['blocks' => []]]);
        Article::create(['title' => 'Veille IA', 'author_id' => User::factory()->admin()->create()->id, 'published_at' => now()->subDay()]);
        Article::create(['title' => 'Brouillon secret', 'author_id' => User::first()->id]);

        $data = $this->terminalData();

        $this->assertSame('Léo Franz', $data['name']);
        $this->assertSame('leo', $data['user']);
        $this->assertSame('leo@example.com', $data['email']);
        $this->assertSame('Laravel', $data['sections']['competences']['Back-end'][0]['name']);
        $this->assertSame('Portfolio', $data['sections']['projets'][0]['title']);
        $this->assertSame(['Veille IA'], array_column($data['sections']['veille'], 'title'));
    }

    public function test_private_pages_are_left_out(): void
    {
        Page::whereIn('key', ['projets', 'contact'])->update(['is_public' => false]);
        Page::flushCache();
        Profile::current()->update(['email' => 'leo@example.com']);

        $data = $this->terminalData();

        $this->assertArrayNotHasKey('projets', $data['sections']);
        $this->assertArrayNotHasKey('projets', $data['pages']);
        $this->assertNull($data['email']);
    }

    public function test_public_pages_link_to_the_terminal(): void
    {
        $this->get(route('home'))->assertSee('data-terminal-url="'.route('terminal').'"', false)->assertSee('&gt;_ terminal', false);
    }
}
