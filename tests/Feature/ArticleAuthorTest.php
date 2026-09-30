<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ServiceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ArticleAuthorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->admin()->create();
    }

    protected function machine(string $role, array $permissions): array
    {
        $account = User::factory()->create(['global_role' => $role, 'permissions' => $permissions, 'is_active' => true]);
        [$token, $plain] = ServiceToken::issue($account, 'Test');

        return [$account, $plain];
    }

    protected function article(User $author): Article
    {
        return Article::create(['title' => 'Veille', 'author_id' => $author->id, 'content' => ['blocks' => []]]);
    }

    protected function form(array $extra = []): array
    {
        return ['title' => 'Veille', 'content' => json_encode(['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Texte']]]]), 'status' => 'draft', 'intent' => 'draft', ...$extra];
    }

    public function test_admin_can_pick_any_active_account_as_author(): void
    {
        [$bot] = $this->machine('bot', []);
        $article = $this->article($this->admin);

        $this->actingAs($this->admin)->get(route('admin.articles.edit', $article))->assertSee($bot->name.' (Bot)');
        $this->put(route('admin.articles.update', $article), $this->form(['author_id' => $bot->id]))->assertSessionHasNoErrors();
        $this->assertTrue($article->fresh()->author->is($bot));

        $bot->update(['is_active' => false]);
        $this->put(route('admin.articles.update', $article), $this->form(['author_id' => $bot->id]))->assertSessionHasErrors('author_id');
    }

    public function test_contributor_hands_over_own_article_and_stays_coauthor(): void
    {
        $contributor = User::factory()->create(['global_role' => 'user']);
        $article = $this->article($contributor);

        $this->actingAs($contributor)->put(route('admin.articles.update', $article), $this->form(['author_id' => $this->admin->id]))->assertSessionHasNoErrors();
        $article->refresh();
        $this->assertTrue($article->author->is($this->admin));
        $this->assertTrue($article->coauthors->contains($contributor));

        // Plus auteur principal : ne peut plus changer l'auteur.
        $this->put(route('admin.articles.update', $article), $this->form(['author_id' => $contributor->id]))->assertSessionHasNoErrors();
        $this->assertTrue($article->fresh()->author->is($this->admin));
    }

    public function test_bot_needs_articles_author_permission(): void
    {
        $other = User::factory()->create(['global_role' => 'user']);
        $article = $this->article($this->admin);

        [, $plain] = $this->machine('bot', ['articles.read', 'articles.write']);
        $this->post(route('login.bot'), ['code' => $plain]);
        $this->put(route('admin.articles.update', $article), $this->form(['author_id' => $other->id]))->assertSessionHasNoErrors();
        $this->assertTrue($article->fresh()->author->is($this->admin));
        $this->post(route('logout'));

        [, $plain] = $this->machine('bot', ['articles.read', 'articles.write', 'articles.author']);
        $this->post(route('login.bot'), ['code' => $plain]);
        $this->put(route('admin.articles.update', $article), $this->form(['author_id' => $other->id]))->assertSessionHasNoErrors();
        $this->assertTrue($article->fresh()->author->is($other));
    }

    public function test_api_needs_articles_author_permission(): void
    {
        [$bot] = $this->machine('bot', []);
        $article = $this->article($this->admin);

        [, $plain] = $this->machine('service', ['articles.write']);
        $this->patchJson("/api/v1/articles/{$article->id}", ['author' => $bot->username], ['Authorization' => 'Bearer '.$plain])->assertForbidden();

        [$service, $plain] = $this->machine('service', ['articles.write', 'articles.author']);
        $this->patchJson("/api/v1/articles/{$article->id}", ['author' => $bot->username], ['Authorization' => 'Bearer '.$plain])->assertOk();
        $this->assertTrue($article->fresh()->author->is($bot));

        $this->patchJson("/api/v1/articles/{$article->id}", ['author' => $service->email], ['Authorization' => 'Bearer '.$plain])->assertOk();
        $this->assertTrue($article->fresh()->author->is($service));

        $this->patchJson("/api/v1/articles/{$article->id}", ['author' => 'inconnu@example.com'], ['Authorization' => 'Bearer '.$plain])->assertUnprocessable();
    }
}
