<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setStatus(string $key, string $status, ?string $password = null): Page
    {
        $page = Page::where('key', $key)->first();
        $page->forceFill(['status' => $status, 'password' => $password])->save();

        return $page;
    }

    public function test_draft_pages_answer_404_except_to_admins(): void
    {
        $this->setStatus('formations', Page::DRAFT);

        $this->get('/formations')->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get('/formations')->assertOk()->assertSee('brouillon');
    }

    public function test_maintenance_pages_answer_503_and_leave_the_menu(): void
    {
        $page = $this->setStatus('formations', Page::MAINTENANCE);

        $this->get('/formations')->assertStatus(503)->assertSee('maintenance');
        $this->get('/contact')->assertOk()->assertDontSee('href="'.$page->url().'"', false);
    }

    public function test_unlisted_pages_are_reachable_but_hidden_and_not_indexed(): void
    {
        $page = $this->setStatus('formations', Page::UNLISTED);

        $this->get('/formations')->assertOk()->assertSee('noindex', false);
        $this->get('/contact')->assertOk()->assertDontSee('href="'.$page->url().'"', false);
        $this->get(route('sitemap'))->assertDontSee($page->url());
    }

    public function test_password_pages_are_unlocked_per_session_and_cover_sub_routes(): void
    {
        $page = $this->setStatus('projets', Page::PASSWORD, 'secret-42');
        $project = \App\Models\Project::forceCreate(['title' => 'Supervision', 'slug' => 'supervision', 'published_on' => '2026-01-01', 'description' => ['blocks' => []]]);

        $this->get('/contact')->assertSee('href="'.$page->url().'"', false); // reste dans le menu
        $this->get('/projets')->assertOk()->assertSee('Saisissez le mot de passe');
        $this->get(route('projects.show', $project))->assertOk()->assertSee('Saisissez le mot de passe');
        $this->get(route('home'))->assertDontSee(route('projects.show', $project)); // contenu non divulgué ailleurs

        $this->from('/projets/supervision')->post(route('pages.unlock', 'projets'), ['password' => 'non'])->assertSessionHasErrors('password');
        $this->from('/projets/supervision')->post(route('pages.unlock', 'projets'), ['password' => 'secret-42'])->assertRedirect('/projets/supervision');
        $this->get(route('projects.show', $project))->assertOk()->assertSee('Supervision')->assertDontSee('Saisissez le mot de passe');

        // Nouveau mot de passe : reverrouillée
        $page->forceFill(['password' => 'autre-42'])->save();
        $this->get('/projets')->assertSee('Saisissez le mot de passe');
    }

    public function test_admin_sets_status_and_password(): void
    {
        $admin = User::factory()->admin()->create();
        $pages = fn (array $contact) => Page::all()->mapWithKeys(fn (Page $p) => [$p->id => [
            'title' => $p->title, 'intro' => $p->intro, 'position' => $p->position, 'status' => $p->status,
            ...($p->key === 'contact' ? $contact : []),
        ]])->all();

        $this->actingAs($admin)->put(route('admin.pages.update'), ['pages' => $pages(['status' => Page::PASSWORD])])->assertSessionHasErrors();
        $this->put(route('admin.pages.update'), ['pages' => $pages(['status' => Page::PASSWORD, 'password' => 'secret-42'])])->assertSessionHasNoErrors();
        $contact = Page::where('key', 'contact')->first();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret-42', $contact->password));

        $this->put(route('admin.pages.update'), ['pages' => $pages(['status' => Page::PASSWORD])])->assertSessionHasNoErrors(); // vide = conservé
        $this->assertNotNull($contact->fresh()->password);

        $this->put(route('admin.pages.update'), ['pages' => $pages(['status' => Page::MAINTENANCE])]);
        $this->assertSame(Page::MAINTENANCE, $contact->fresh()->status);
        $this->assertNull($contact->fresh()->password);
    }
}
