<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\ServiceToken;
use App\Models\Skill;
use App\Models\Theme;
use App\Models\User;
use App\Services\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        User::factory()->admin()->create(['email' => 'admin@example.com']);
    }

    /** @return array{0: User, 1: array<string, string>} */
    protected function account(array $permissions, bool $active = true): array
    {
        $account = User::create([
            'name' => 'Robot', 'username' => 'svc-robot-'.uniqid(), 'email' => uniqid().'@service.invalid', 'password' => 'x',
            'global_role' => 'service', 'permissions' => $permissions, 'is_active' => $active,
        ]);
        [, $plain] = ServiceToken::issue($account, 'Test');

        return [$account, ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json']];
    }

    public function test_authentication_failures_are_rejected_and_logged(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer pfs_faux'])->assertUnauthorized();

        [, $headers] = $this->account(['articles.read'], active: false);
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();

        $this->assertSame(3, AuditLog::where('action', 'api.auth_failed')->count());
        // Seul le préfixe (12 caractères, affiché aussi dans le panel) est journalisé, jamais le code entier.
        $plain = substr($headers['Authorization'], 7);
        $this->assertStringNotContainsString($plain, json_encode(AuditLog::all(), JSON_UNESCAPED_SLASHES));
        $this->assertStringContainsString(substr($plain, 0, 12), json_encode(AuditLog::pluck('meta'), JSON_UNESCAPED_SLASHES));
    }

    public function test_me_endpoint_and_last_use(): void
    {
        [$account, $headers] = $this->account(['articles.read']);

        $this->getJson('/api/v1/me', $headers)->assertOk()
            ->assertJsonPath('data.name', 'Robot')
            ->assertJsonPath('data.permissions', ['articles.read' => 'Lire les articles (brouillons compris)']);

        $this->assertNotNull($account->serviceTokens()->first()->last_used_at);
    }

    public function test_every_route_requires_its_permission(): void
    {
        [, $headers] = $this->account([]);
        $article = Article::create(['title' => 'A', 'author_id' => User::first()->id]);

        foreach ([
            ['GET', '/api/v1/articles'], ['POST', '/api/v1/articles'], ['DELETE', "/api/v1/articles/{$article->id}"],
            ['POST', "/api/v1/articles/{$article->id}/publish"], ['GET', '/api/v1/projects'], ['POST', '/api/v1/projects'],
            ['GET', '/api/v1/announcements'], ['POST', '/api/v1/announcements'], ['GET', '/api/v1/messages'],
            ['GET', '/api/v1/export'], ['POST', '/api/v1/import'], ['GET', '/api/v1/maintenance'],
        ] as [$method, $uri]) {
            $this->json($method, $uri, [], $headers)->assertForbidden()->assertJsonStructure(['message', 'permission']);
        }

        $this->assertSame(12, AuditLog::where('action', 'api.forbidden')->count());
        $this->assertSame('articles.read', AuditLog::where('action', 'api.forbidden')->oldest('id')->first()->meta['autorisation']);
    }

    public function test_writer_creates_a_draft_in_markdown_and_submits_it_for_review(): void
    {
        [$account, $headers] = $this->account(['articles.read', 'articles.write']);
        Theme::create(['name' => 'Réseau']);

        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Article API', 'content_markdown' => "## Intro\n\nTexte **gras**", 'themes' => ['réseau'], 'submit_for_review' => true,
        ], $headers)->assertCreated()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.themes', ['Réseau']);

        $article = Article::find($response->json('data.id'));
        $this->assertSame(['header', 'paragraph'], array_column($article->content['blocks'], 'type'));
        $this->assertTrue($article->author->is($account));
        Mail::assertSent(\App\Mail\ArticleReviewNotification::class, fn ($m) => $m->hasTo('admin@example.com'));

        // Publication refusée sans l'autorisation dédiée
        $this->patchJson("/api/v1/articles/{$article->id}", ['status' => 'published'], $headers)->assertForbidden();
        $this->assertNull($article->fresh()->published_at);

        // Thème inconnu : erreur explicite
        $this->patchJson("/api/v1/articles/{$article->id}", ['themes' => ['Inexistant']], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('themes');

        $log = AuditLog::where('action', 'article.created')->sole();
        $this->assertSame('api', $log->via);
        $this->assertSame('Robot', $log->actor_name);
        $this->assertSame('service', $log->actor_role);
    }

    public function test_publisher_can_publish_and_read_markdown(): void
    {
        [, $headers] = $this->account(['articles.read', 'articles.write', 'articles.publish', 'articles.delete']);

        $id = $this->postJson('/api/v1/articles', ['title' => 'Publié', 'content_html' => '<p>Du <b>HTML</b></p>', 'status' => 'published', 'is_pinned' => true], $headers)
            ->assertCreated()->assertJsonPath('data.status', 'published')->json('data.id');

        $this->getJson("/api/v1/articles/{$id}", $headers)->assertOk()->assertJsonPath('data.content_markdown', "Du **HTML**\n");
        $this->getJson('/api/v1/articles?status=published', $headers)->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/articles/{$id}/unpublish", [], $headers)->assertJsonPath('data.status', 'draft');
        $this->deleteJson("/api/v1/articles/{$id}", [], $headers)->assertNoContent();
        $this->assertSame(1, AuditLog::where('action', 'article.deleted')->where('via', 'api')->count());
    }

    public function test_projects_announcements_messages(): void
    {
        [, $headers] = $this->account(['projects.read', 'projects.write', 'announcements.read', 'announcements.write', 'messages.read']);
        Skill::create(['name' => 'Laravel']);

        $this->postJson('/api/v1/projects', ['title' => 'Sans description', 'published_on' => '2026-01-01'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('description');

        $id = $this->postJson('/api/v1/projects', [
            'title' => 'Projet API', 'published_on' => '2026-01-01', 'description_markdown' => '- A', 'skills' => ['Laravel'],
            'links' => [['url' => 'https://github.com/x/y']],
        ], $headers)->assertCreated()->assertJsonPath('data.skills', ['Laravel'])->json('data.id');
        $this->assertSame('markdown', Project::find($id)->description_editor);
        $this->deleteJson("/api/v1/projects/{$id}", [], $headers)->assertForbidden();

        $this->postJson('/api/v1/announcements', ['title' => 'Alternance', 'style' => 'success'], $headers)->assertCreated();
        $this->assertSame(1, Announcement::count());

        ContactMessage::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.c', 'subject' => 'S', 'message' => 'M', 'consented_at' => now()]);
        $this->getJson('/api/v1/messages', $headers)->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertNotNull(AuditLog::where('action', 'api.messages_read')->first());
    }

    public function test_export_import_and_maintenance(): void
    {
        [, $headers] = $this->account(['content.export', 'content.import', 'maintenance.manage']);

        $this->getJson('/api/v1/export?sections[]=themes', $headers)->assertOk()->assertJsonPath('format', 'portfolio');

        $payload = ['sections' => ['themes' => [['name' => 'Cloud']]]];
        $this->postJson('/api/v1/import', ['data' => $payload], $headers)->assertUnprocessable()->assertJsonValidationErrors('dry_run');
        $this->postJson('/api/v1/import', ['data' => $payload, 'dry_run' => true], $headers)->assertOk()->assertJsonPath('report.themes.created', 1);
        $this->assertSame(0, Theme::count());
        $this->postJson('/api/v1/import', ['data' => $payload, 'dry_run' => false], $headers)->assertOk();
        $this->assertSame(1, Theme::count());

        $this->putJson('/api/v1/maintenance', ['enabled' => true, 'reason' => 'Mise à jour'], $headers)->assertJsonPath('data.enabled', true);
        $this->getJson('/api/v1/maintenance', $headers)->assertJsonPath('data.reason', 'Mise à jour');
        app(Maintenance::class)->disable();
    }

    public function test_requests_are_rate_limited_per_code(): void
    {
        [, $headers] = $this->account(['articles.read']);

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/me', $headers)->assertOk();
        }

        $this->getJson('/api/v1/me', $headers)->assertStatus(429);
    }
}
