<?php

namespace Tests\Feature;

use App\Mail\ArticleReviewNotification;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContributorArticleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $contributor;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
        $this->contributor = User::factory()->create(['email' => 'contrib@example.com']);
    }

    protected function content(string $text = 'Texte'): string
    {
        return json_encode(['blocks' => [['type' => 'paragraph', 'data' => ['text' => $text]]]]);
    }

    protected function articleBy(User $author, array $attributes = []): Article
    {
        return Article::create(array_merge(['title' => 'Article de '.$author->name, 'author_id' => $author->id, 'content' => ['blocks' => []]], $attributes));
    }

    public function test_contributor_accesses_only_the_article_editor(): void
    {
        $this->actingAs($this->contributor);

        $this->get(route('admin.articles.index'))->assertOk()->assertSee('Articles de veille')->assertDontSee('Tableau de bord');
        $this->get(route('admin.articles.create'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.projets.index'))->assertForbidden();
        $this->post(route('admin.themes.quick'), ['name' => 'X'])->assertForbidden();
    }

    public function test_contributor_can_view_every_article_including_drafts(): void
    {
        $draft = $this->articleBy($this->admin, ['title' => 'Brouillon admin', 'content' => ['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Contenu secret']]]]]);

        $this->actingAs($this->contributor)->get(route('admin.articles.index'))->assertSee('Brouillon admin');
        $this->actingAs($this->contributor)->get(route('admin.articles.show', $draft))
            ->assertOk()->assertSee('Contenu secret')->assertDontSee(route('admin.articles.edit', $draft));
    }

    public function test_contributor_edits_only_articles_they_author_or_coauthor(): void
    {
        $other = $this->articleBy($this->admin);
        $own = $this->articleBy($this->contributor);
        $coauthored = $this->articleBy($this->admin, ['title' => 'Co-écrit']);
        $coauthored->coauthors()->attach($this->contributor);

        $this->actingAs($this->contributor);
        $this->get(route('admin.articles.edit', $other))->assertForbidden();
        $this->put(route('admin.articles.update', $other), ['title' => 'Piraté'])->assertForbidden();
        $this->get(route('admin.articles.edit', $own))->assertOk();
        $this->get(route('admin.articles.edit', $coauthored))->assertOk();

        $this->put(route('admin.articles.update', $coauthored), ['title' => 'Co-écrit modifié', 'content' => $this->content(), 'coauthors' => []])
            ->assertSessionHasNoErrors();
        $this->assertSame('Co-écrit modifié', $coauthored->fresh()->title);
        // En retirant tous les co-auteurs, le contributeur reste co-auteur (il garde l'accès).
        $this->assertTrue($coauthored->fresh()->coauthors->contains($this->contributor));
        $this->assertSame($this->admin->id, $coauthored->fresh()->author_id);

        $this->delete(route('admin.articles.destroy', $own))->assertForbidden();
    }

    public function test_contributor_articles_stay_drafts_and_cannot_be_published_or_pinned(): void
    {
        $this->actingAs($this->contributor)->post(route('admin.articles.store'), [
            'title' => 'Mon article', 'content' => $this->content(),
            'status' => 'published', 'published_at' => now()->subDay()->toDateTimeString(), 'is_pinned' => '1',
            'author_id' => $this->admin->id, 'intent' => 'draft',
        ])->assertSessionHasNoErrors();

        $article = Article::sole();
        $this->assertNull($article->published_at);
        $this->assertFalse($article->is_pinned);
        $this->assertTrue($article->author->is($this->contributor));
        $this->assertNull($article->review_status);
        $this->get(route('articles.show', $article))->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_submission_then_admin_approval_publishes_the_article(): void
    {
        $this->actingAs($this->contributor)->post(route('admin.articles.store'), [
            'title' => 'À valider', 'content' => $this->content(), 'intent' => 'submit',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'soumis'));

        $article = Article::sole();
        $this->assertTrue($article->isPendingReview());
        $this->assertSame('En attente de validation', $article->status());
        Mail::assertSent(ArticleReviewNotification::class, fn ($mail) => $mail->hasTo('admin@example.com') && $mail->event === 'submitted');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('Articles à valider')->assertSee('À valider');
        $this->actingAs($this->admin)->get(route('admin.articles.index', ['filtre' => 'a-valider']))->assertSee('À valider');
        $this->actingAs($this->admin)->get(route('admin.articles.show', $article))->assertSee('Valider et publier');

        // Un contributeur ne peut pas valider.
        $this->actingAs($this->contributor)->post(route('admin.articles.approve', $article))->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.articles.approve', $article))->assertSessionHas('success');

        $article->refresh();
        $this->assertTrue($article->isPublished());
        $this->assertNull($article->review_status);
        $this->assertTrue($article->reviewer->is($this->admin));
        Mail::assertSent(ArticleReviewNotification::class, fn ($mail) => $mail->hasTo('contrib@example.com') && $mail->event === 'approved');

        auth()->logout();
        $this->get(route('articles.show', $article))->assertOk();
    }

    public function test_admin_sends_the_article_back_with_a_comment(): void
    {
        $article = $this->articleBy($this->contributor, ['review_status' => Article::REVIEW_PENDING, 'submitted_at' => now()]);

        $this->actingAs($this->admin)->post(route('admin.articles.request-changes', $article), ['review_note' => ''])
            ->assertSessionHasErrors('review_note');
        $this->actingAs($this->admin)->post(route('admin.articles.request-changes', $article), ['review_note' => 'Ajoute des sources.'])
            ->assertSessionHas('success');

        $article->refresh();
        $this->assertSame('À retravailler', $article->status());
        Mail::assertSent(ArticleReviewNotification::class, fn ($mail) => $mail->event === 'changes_requested');

        $this->actingAs($this->contributor)->get(route('admin.articles.edit', $article))->assertSee('Ajoute des sources.');

        // Nouvelle soumission après corrections
        $this->actingAs($this->contributor)->put(route('admin.articles.update', $article), [
            'title' => $article->title, 'content' => $this->content('Avec sources'), 'intent' => 'submit',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($article->fresh()->isPendingReview());
    }

    public function test_saving_as_draft_withdraws_the_submission(): void
    {
        $article = $this->articleBy($this->contributor, ['review_status' => Article::REVIEW_PENDING, 'submitted_at' => now()]);

        $this->actingAs($this->contributor)->put(route('admin.articles.update', $article), [
            'title' => $article->title, 'content' => $this->content(), 'intent' => 'draft',
        ]);

        $this->assertNull($article->fresh()->review_status);
    }

    public function test_contributor_edit_of_a_published_article_keeps_it_published(): void
    {
        $article = $this->articleBy($this->contributor, ['published_at' => now()->subDay(), 'is_pinned' => true]);

        $this->actingAs($this->contributor)->put(route('admin.articles.update', $article), [
            'title' => 'Corrigé', 'content' => $this->content(), 'status' => 'draft', 'is_pinned' => '0',
        ])->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertTrue($article->isPublished());
        $this->assertTrue($article->is_pinned);
        $this->assertSame('Corrigé', $article->title);
    }

    public function test_contributor_sees_draft_attachments_even_if_the_veille_page_is_private(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \App\Models\Page::where('key', 'veille')->update(['is_public' => false]);
        $article = $this->articleBy($this->contributor);

        $this->actingAs($this->contributor)->put(route('admin.articles.update', $article), [
            'title' => $article->title, 'content' => $this->content(),
            'files' => [\Illuminate\Http\UploadedFile::fake()->image('schema.png')],
        ]);
        $url = $article->fresh()->files->first()->url();

        $this->actingAs($this->contributor)->get($url)->assertOk();
        auth()->logout();
        $this->get($url)->assertNotFound();
    }
}
