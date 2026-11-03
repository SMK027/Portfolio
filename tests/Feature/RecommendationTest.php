<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function submission(array $overrides = []): array
    {
        return $overrides + [
            'author_name' => 'Claire Martin', 'author_role' => 'Responsable informatique', 'author_company' => 'Société X',
            'relationship' => 'Tutrice de stage', 'author_url' => 'https://www.linkedin.com/in/claire',
            'content' => 'Un stagiaire rigoureux et curieux, qui a su prendre en main nos outils très rapidement.', 'consent' => '1',
        ];
    }

    protected function publishPage(): void
    {
        Page::where('key', 'recommandations')->update(['is_public' => true]);
        Page::flushCache();
    }

    public function test_admin_creates_an_invitation_with_a_personal_link(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.recommendations.index'))->assertOk()->assertSee('Nouvelle invitation');
        $this->actingAs($admin)->post(route('admin.recommendations.store'), ['label' => 'M. Martin'])
            ->assertRedirect(route('admin.recommendations.index'));

        $recommendation = Recommendation::sole();
        $this->assertSame(Recommendation::INVITED, $recommendation->status);
        $this->assertSame(48, strlen($recommendation->token));
        $this->actingAs($admin)->get(route('admin.recommendations.index'))->assertSee($recommendation->token);
    }

    public function test_author_submits_then_can_edit_until_publication(): void
    {
        $recommendation = Recommendation::invite('M. Martin', null);

        $this->get($recommendation->inviteUrl())->assertOk()->assertSee('Rédiger une recommandation')->assertSee('noindex', false);
        $this->post(route('recommendations.submit', $recommendation->token), $this->submission())
            ->assertRedirect($recommendation->inviteUrl())->assertSessionHas('success');

        $recommendation->refresh();
        $this->assertSame(Recommendation::PENDING, $recommendation->status);
        $this->assertSame('Claire Martin', $recommendation->author_name);
        $this->assertNotNull($recommendation->consented_at);

        $this->post(route('recommendations.submit', $recommendation->token), $this->submission(['content' => str_repeat('Texte corrigé. ', 5)]))
            ->assertSessionHas('success');
        $this->assertStringStartsWith('Texte corrigé.', $recommendation->fresh()->content);

        // Publiée : le lien se ferme.
        $recommendation->forceFill(['status' => Recommendation::APPROVED])->save();
        $this->get($recommendation->inviteUrl())->assertOk()->assertSee('Votre recommandation est publiée')->assertDontSee('name="content"', false);
        $this->post(route('recommendations.submit', $recommendation->token), $this->submission())->assertNotFound();
    }

    public function test_submission_is_validated_and_expired_links_are_closed(): void
    {
        $recommendation = Recommendation::invite('M. Martin', null);

        $this->post(route('recommendations.submit', $recommendation->token), $this->submission(['content' => 'Trop court', 'consent' => null]))
            ->assertSessionHasErrors(['content', 'consent']);
        $this->post(route('recommendations.submit', $recommendation->token), $this->submission(['website' => 'spam']))
            ->assertSessionHasErrors('website');

        $expired = Recommendation::invite('Expiré', now()->subDay());
        $this->post(route('recommendations.submit', $expired->token), $this->submission())->assertNotFound();
        $this->get(route('recommendations.invite', str_repeat('a', 48)))->assertNotFound();
    }

    public function test_only_approved_recommendations_are_published_and_featured_ones_on_home(): void
    {
        $this->publishPage();
        $admin = User::factory()->admin()->create();
        $recommendation = Recommendation::invite('M. Martin', null);
        $this->post(route('recommendations.submit', $recommendation->token), $this->submission());

        $this->get(route('recommendations.index'))->assertOk()->assertDontSee('Claire Martin');

        $this->actingAs($admin)->put(route('admin.recommendations.update', $recommendation), [
            'label' => 'M. Martin', 'status' => Recommendation::APPROVED, 'is_featured' => '1',
            ...collect($this->submission())->except('consent')->all(),
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($recommendation->fresh()->approved_at);
        auth()->logout();

        $this->get(route('recommendations.index'))->assertSee('Claire Martin')->assertSee('Responsable informatique · Société X');
        $this->get(route('home'))->assertSee('Ils me recommandent')->assertSee('Claire Martin');
    }

    public function test_publishing_requires_an_author_and_a_text(): void
    {
        $admin = User::factory()->admin()->create();
        $recommendation = Recommendation::invite('M. Martin', null);

        $this->actingAs($admin)->put(route('admin.recommendations.update', $recommendation), ['label' => 'M. Martin', 'status' => Recommendation::APPROVED])
            ->assertSessionHasErrors(['author_name', 'content']);
    }

    public function test_private_page_hides_the_list_but_not_the_invitation(): void
    {
        $recommendation = Recommendation::invite('M. Martin', null);

        $this->get(route('recommendations.index'))->assertNotFound();
        $this->get($recommendation->inviteUrl())->assertOk();
        $this->get(route('home'))->assertDontSee('Ils me recommandent');
    }

    public function test_bots_need_the_recommendation_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.recommendations.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.recommendations.store'), ['label' => 'X'])->assertForbidden();
    }
}
