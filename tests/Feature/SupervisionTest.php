<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Certification;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\Supervision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupervisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
        $this->staff = User::factory()->create(['global_role' => 'staff', 'permissions' => ['skills.read', 'projects.read'], 'is_active' => true]);
    }

    protected function supervisor(array $permissions = ['skills.write', 'skills.delete', 'projects.write', 'certifications.read', 'articles.delete'], array $extra = []): Supervisor
    {
        $supervisor = new Supervisor(['username' => 'chef', 'user_id' => $this->admin->id, 'permissions' => $permissions, 'is_active' => true, ...$extra]);
        $supervisor->setPin('4821');
        $supervisor->save();

        return $supervisor;
    }

    /** Valide la requête en attente et renvoie [action, champs] du formulaire de rejeu. */
    protected function approve(string $pin = '4821'): array
    {
        $html = $this->post(route('supervision.store'), ['username' => 'chef', 'pin' => $pin])->assertOk()->getContent();
        preg_match('/action="([^"]+)" id="supervision-replay"/', $html, $action);
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"/', $html, $m, PREG_SET_ORDER);

        return [html_entity_decode($action[1]), collect($m)->mapWithKeys(fn ($f) => [html_entity_decode($f[1]) => html_entity_decode($f[2])])->all()];
    }

    public function test_unauthorized_form_is_held_validated_once_and_replayed(): void
    {
        $this->supervisor();
        $this->actingAs($this->staff)->post(route('admin.competences.store'), ['name' => 'Kubernetes', 'category' => 'DevOps'])
            ->assertRedirect(route('supervision.show'));
        $this->assertSame(0, Skill::count());
        $this->get(route('supervision.show'))->assertOk()->assertSee('POST /admin/competences', false);

        $this->post(route('supervision.store'), ['username' => 'chef', 'pin' => '0000'])->assertSessionHasErrors('pin');
        $this->assertNotNull(AuditLog::where('action', 'supervision.failed')->first());

        [$action, $fields] = $this->approve();
        $this->assertSame('Kubernetes', $fields['name']);
        $this->assertNull(session(Supervision::PENDING_KEY)); // plus rien en session

        $this->post($action, $fields)->assertRedirect();
        $this->assertSame('Kubernetes', Skill::sole()->name);
        $this->assertNotNull(AuditLog::where('action', 'supervision.granted')->first());

        // Usage unique : rejouer le même jeton ne passe plus.
        $this->post($action, $fields)->assertRedirect(route('supervision.show'));
        $this->assertSame(1, Skill::count());
    }

    public function test_consultation_bypass_is_not_persistent(): void
    {
        $this->supervisor();
        Certification::create(['name' => 'CCNA', 'issuer' => 'Cisco', 'issued_at' => '2026-01-01']);

        $this->actingAs($this->staff)->get(route('admin.certifications.index'))->assertRedirect(route('supervision.show'));
        $redirect = $this->post(route('supervision.store'), ['username' => 'chef', 'pin' => '4821'])->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString(Supervision::BYPASS_FIELD.'=', $redirect);

        $this->get($redirect)->assertOk()->assertSee('CCNA');
        $this->get($redirect)->assertRedirect(route('supervision.show'));            // jeton consommé
        $this->get(route('admin.certifications.index'))->assertRedirect(route('supervision.show')); // rien en session
    }

    public function test_files_are_kept_and_replayed(): void
    {
        $this->supervisor();
        $this->actingAs($this->staff)->post(route('admin.projets.store'), [
            'title' => 'Supervision Zabbix', 'published_on' => '2026-09-01',
            'description' => json_encode(['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Texte']]]]),
            'files' => [UploadedFile::fake()->create('rapport.pdf', 50, 'application/pdf')],
        ])->assertRedirect(route('supervision.show'));
        $this->assertCount(1, Storage::disk('local')->allFiles('supervision'));

        [$action, $fields] = $this->approve();
        $this->post($action, $fields)->assertRedirect()->assertSessionHasNoErrors();

        $project = Project::sole();
        $this->assertSame('rapport.pdf', $project->files()->sole()->original_name);
    }

    public function test_supervisor_restrictions(): void
    {
        $supervisor = $this->supervisor(['skills.write']);
        $skill = Skill::create(['name' => 'PHP']);

        // Opération qu'aucun superviseur ne peut valider : refus direct.
        $this->actingAs($this->staff)->delete(route('admin.competences.destroy', $skill))->assertForbidden();

        // Désactivé, puis administrateur rattaché désactivé.
        $this->post(route('admin.competences.store'), ['name' => 'Go'])->assertRedirect(route('supervision.show'));
        $supervisor->update(['is_active' => false]);
        $this->post(route('supervision.store'), ['username' => 'chef', 'pin' => '4821'])->assertSessionHasErrors('pin');
        $supervisor->update(['is_active' => true]);
        $this->admin->update(['is_active' => false]);
        $this->post(route('supervision.store'), ['username' => 'chef', 'pin' => '4821'])->assertSessionHasErrors('pin');
        $this->assertSame(1, Skill::count());
    }

    public function test_no_eligible_supervisor_means_plain_refusal(): void
    {
        $this->actingAs($this->staff)->post(route('admin.competences.store'), ['name' => 'Go'])->assertForbidden();
        $this->supervisor(['projects.write']);
        $this->post(route('admin.competences.store'), ['name' => 'Go'])->assertForbidden();
    }

    public function test_contributor_and_admin_accounts(): void
    {
        $this->supervisor();
        $contributor = User::factory()->create(['global_role' => 'user']);
        $article = Article::create(['title' => 'Veille', 'author_id' => $this->admin->id, 'content' => ['blocks' => []]]);

        $this->actingAs($contributor)->delete(route('admin.articles.destroy', $article))->assertRedirect(route('supervision.show'));
        [$action, $fields] = $this->approve();
        $this->post($action, $fields)->assertRedirect();
        $this->assertModelMissing($article);

        // Jamais de bypass pour modifier un compte administrateur.
        $staff = User::factory()->create(['global_role' => 'staff', 'permissions' => ['users.read'], 'is_active' => true]);
        $this->supervisor(['users.write'], ['username' => 'chef2']);
        $this->actingAs($staff)->get(route('admin.utilisateurs.edit', $this->admin))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.utilisateurs.edit', $contributor))->assertRedirect(route('supervision.show'));
    }

    public function test_supervisor_management_and_pin_rules(): void
    {
        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super)->post(route('admin.supervisors.store'), [
            'username' => 'chef', 'user_id' => $this->admin->id, 'pin' => '12345', 'pin_confirmation' => '12345',
            'is_active' => '1', 'permissions' => ['skills.write'],
        ])->assertRedirect();
        $supervisor = Supervisor::sole();
        $this->assertNotSame('12345', $supervisor->pin_hash);
        $this->assertTrue($supervisor->checkPin('12345'));
        $this->assertStringNotContainsString('12345', AuditLog::all()->toJson());

        $this->put(route('admin.supervisors.update', $supervisor), ['username' => 'chef', 'user_id' => $this->admin->id, 'pin' => '123', 'pin_confirmation' => '123', 'permissions' => ['skills.write']])
            ->assertSessionHasErrors('pin');
        // Réattribution : nouveau PIN obligatoire.
        $this->put(route('admin.supervisors.update', $supervisor), ['username' => 'chef', 'user_id' => $super->id, 'permissions' => ['skills.write']])
            ->assertSessionHasErrors('pin');
        $this->put(route('admin.supervisors.update', $supervisor), ['username' => 'chef', 'user_id' => $super->id, 'pin' => '987654', 'pin_confirmation' => '987654', 'permissions' => ['skills.write']])
            ->assertSessionHasNoErrors();
        $this->assertTrue($supervisor->fresh()->checkPin('987654'));
        // Rattachement : administrateurs seulement.
        $this->put(route('admin.supervisors.update', $supervisor), ['username' => 'chef', 'user_id' => $this->staff->id, 'pin' => '1111', 'pin_confirmation' => '1111', 'permissions' => ['skills.write']])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($this->admin)->get(route('admin.supervisors.index'))->assertForbidden();
    }

    public function test_every_supervision_step_is_logged(): void
    {
        $supervisor = $this->supervisor();
        $log = fn (string $action) => AuditLog::where('action', $action)->latest('id')->first();

        $this->actingAs($this->staff)->post(route('admin.competences.store'), ['name' => 'Go']);
        foreach (range(1, 5) as $i) {
            $this->post(route('supervision.store'), ['username' => 'chef', 'pin' => '0000']);
        }
        $this->assertSame(5, AuditLog::where('action', 'supervision.failed')->count());
        $this->assertSame('identifiant, PIN ou superviseur invalide', $log('supervision.failed')->meta['raison']);
        $this->assertSame($this->staff->id, $log('supervision.failed')->user_id); // compte demandeur
        $this->post(route('supervision.store'), ['username' => 'chef', 'pin' => '4821'])->assertSessionHasErrors('pin');
        $this->assertNotNull($log('supervision.locked'));
        \Illuminate\Support\Facades\RateLimiter::clear('supervision:'.$this->staff->id.'|chef');

        [$action, $fields] = $this->approve();
        $granted = $log('supervision.granted');
        $this->assertSame($supervisor->id, $granted->subject_id);
        $this->assertSame('POST /admin/competences', $granted->meta['requête']);

        $this->post($action, $fields);
        $used = $log('supervision.used');
        $this->assertSame(['skills.write'], $used->meta['opérations']);
        $this->assertSame($supervisor->id, $used->subject_id);

        $this->post($action, $fields); // jeton réutilisé
        $this->assertSame('jeton inconnu, expiré ou déjà utilisé', $log('supervision.rejected')->meta['raison']);

        $this->delete(route('supervision.destroy'));
        $this->assertNotNull($log('supervision.cancelled'));
        $this->assertStringNotContainsString('4821', AuditLog::all()->toJson()); // jamais le PIN
    }
}
