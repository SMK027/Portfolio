<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Services\GithubRepositories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_extraction(): void
    {
        $this->assertSame('laravel/framework', GithubRepositories::slug('https://github.com/laravel/framework'));
        $this->assertSame('smk/portfolio', GithubRepositories::slug('https://www.github.com/smk/portfolio.git'));
        $this->assertSame('smk/portfolio', GithubRepositories::slug('https://github.com/smk/portfolio/tree/main/src'));
        $this->assertNull(GithubRepositories::slug('https://github.com/smk'));
        $this->assertNull(GithubRepositories::slug('https://gitlab.com/smk/portfolio'));
        $this->assertNull(GithubRepositories::slug('https://github.com/orgs/laravel'));
    }

    public function test_project_page_shows_repository_details_with_cache(): void
    {
        Http::fake(['api.github.com/repos/smk/portfolio' => Http::response([
            'full_name' => 'smk/portfolio', 'html_url' => 'https://github.com/smk/portfolio', 'description' => 'Mon portfolio Laravel',
            'stargazers_count' => 1234, 'forks_count' => 56, 'language' => 'PHP', 'license' => ['spdx_id' => 'MIT'],
            'archived' => false, 'pushed_at' => now()->subDays(3)->toIso8601String(), 'topics' => ['laravel', 'portfolio'],
        ])]);

        $project = Project::create(['title' => 'Portfolio', 'published_on' => now(), 'description' => ['blocks' => []]]);
        $project->links()->create(['url' => 'https://github.com/smk/portfolio']);

        $this->get(route('projects.show', $project))->assertOk()
            ->assertSee('Mon portfolio Laravel')->assertSee('1 234')->assertSee('PHP')->assertSee('MIT')->assertSee('il y a 3 jours');
        $this->get(route('projects.show', $project))->assertOk();
        Http::assertSentCount(1); // cache
    }

    public function test_github_failure_does_not_break_the_page(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'API rate limit exceeded'], 403)]);
        $project = Project::create(['title' => 'Portfolio', 'published_on' => now(), 'description' => ['blocks' => []]]);
        $project->links()->create(['url' => 'https://github.com/smk/introuvable']);

        $this->get(route('projects.show', $project))->assertOk()->assertSee('Dépôt GitHub')->assertDontSee('★');
        $this->assertFalse(Cache::get('github.repo.smk/introuvable'));
    }
}
