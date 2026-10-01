<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Support\LineDiff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function blocks(string $text): array
    {
        return ['blocks' => [['type' => 'header', 'data' => ['level' => 2, 'text' => 'Intro']], ['type' => 'paragraph', 'data' => ['text' => $text]]]];
    }

    public function test_line_diff(): void
    {
        $this->assertSame([
            ['type' => 'same', 'line' => 'a'], ['type' => 'removed', 'line' => 'b'], ['type' => 'added', 'line' => 'x'], ['type' => 'same', 'line' => 'c'],
        ], LineDiff::compare(['a', 'b', 'c'], ['a', 'x', 'c']));
    }

    public function test_versions_are_recorded_on_text_changes_only(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $article = Article::create(['title' => 'V1', 'author_id' => $admin->id, 'content' => $this->blocks('Premier jet')]);
        $article->update(['content' => $this->blocks('Second jet')]);
        $article->update(['is_pinned' => true]); // pas de nouvelle version

        $this->assertSame(2, $article->revisions()->count());
        $this->assertSame($admin->name, $article->revisions()->first()->user_name);
    }

    public function test_compare_and_restore(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $article = Article::create(['title' => 'Titre', 'author_id' => $admin->id, 'content' => $this->blocks('Premier jet')]);
        $first = $article->revisions()->first();
        $article->update(['title' => 'Titre revu', 'content' => $this->blocks('Second jet')]);
        $second = $article->revisions()->first();

        $this->get(route('admin.articles.revisions.index', $article))->assertOk()->assertSee('actuelle');
        $this->get(route('admin.articles.revisions.show', [$article, $second]))->assertOk()
            ->assertSee('Premier jet')->assertSee('Second jet');
        $this->get(route('admin.articles.revisions.show', [$article, $second]))->assertDontSee('Restaurer cette version');
        $this->get(route('admin.articles.revisions.show', [$article, $first, 'avec' => 'actuelle']))->assertSee('Titre revu')->assertSee('Restaurer cette version');

        $this->post(route('admin.articles.revisions.restore', [$article, $first]))->assertRedirect();
        $article->refresh();
        $this->assertSame('Titre', $article->title);
        $this->assertSame('Premier jet', $article->content['blocks'][1]['data']['text']);
        $this->assertSame(3, $article->revisions()->count());
        $this->assertStringStartsWith('Restauration de la version du', $article->revisions()->first()->note);
    }

    public function test_permissions_and_rotation(): void
    {
        $admin = User::factory()->admin()->create();
        $other = Article::create(['title' => 'A', 'author_id' => $admin->id, 'content' => $this->blocks('x')]);
        $contributor = User::factory()->create(['global_role' => 'user']);

        // Contributeur : consulte l'historique, ne restaure pas un article qui n'est pas le sien.
        $this->actingAs($contributor)->get(route('admin.articles.revisions.index', $other))->assertOk();
        $this->post(route('admin.articles.revisions.restore', [$other, $other->revisions()->first()]))->assertForbidden();

        foreach (range(1, ArticleRevision::KEEP + 3) as $i) {
            $other->update(['title' => 'A'.$i]);
        }
        $this->assertSame(ArticleRevision::KEEP, $other->revisions()->count());
    }

    public function test_api_changes_are_versioned(): void
    {
        $account = User::factory()->create(['global_role' => 'service', 'permissions' => ['articles.write'], 'is_active' => true]);
        [, $plain] = \App\Models\ServiceToken::issue($account, 'Test');
        $article = Article::create(['title' => 'A', 'author_id' => User::factory()->admin()->create()->id, 'content' => $this->blocks('x')]);

        $this->patchJson("/api/v1/articles/{$article->id}", ['title' => 'Depuis l\'API'], ['Authorization' => 'Bearer '.$plain])->assertOk();
        $this->assertSame($account->name, $article->revisions()->first()->user_name);
    }
}
