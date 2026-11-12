<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Theme;
use App\Models\User;
use App\Services\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->superAdmin()->create(['email' => 'root@example.com', 'name' => 'Léo']);
    }

    protected function last(string $action): ?AuditLog
    {
        return AuditLog::where('action', $action)->latest('id')->first();
    }

    public function test_authentication_is_logged(): void
    {
        $this->post('/login', ['email' => 'root@example.com', 'password' => 'mauvais']);
        $failed = $this->last('auth.failed');
        $this->assertNotNull($failed);
        $this->assertSame('root@example.com', $failed->meta['email']);
        $this->assertArrayNotHasKey('password', $failed->meta);

        $this->post('/login', ['email' => 'root@example.com', 'password' => 'password']);
        $this->assertSame('Léo', $this->last('auth.login')->actor_name);
        $this->assertSame('web', $this->last('auth.login')->via);

        $this->post('/logout');
        $this->assertNotNull($this->last('auth.logout'));
        $this->assertNull($this->last('user.updated'), 'le jeton « se souvenir de moi » n\'est pas une modification du compte');
    }

    public function test_admin_changes_are_logged_with_field_differences(): void
    {
        $this->actingAs($this->admin)->post(route('admin.annonces.store'), ['title' => 'Recherche de stage', 'style' => 'success', 'is_active' => '1']);
        $announcement = Announcement::sole();
        $created = $this->last('announcement.created');
        $this->assertSame('Recherche de stage', $created->subject_label);
        $this->assertSame('Recherche de stage', $created->changes['title']['new']);

        $this->actingAs($this->admin)->put(route('admin.annonces.update', $announcement), ['title' => 'Recherche d\'alternance', 'style' => 'success', 'is_active' => '1']);
        $updated = $this->last('announcement.updated');
        $this->assertSame(['old' => 'Recherche de stage', 'new' => 'Recherche d\'alternance'], $updated->changes['title']);
        $this->assertArrayNotHasKey('updated_at', $updated->changes);

        $this->actingAs($this->admin)->delete(route('admin.annonces.destroy', $announcement));
        $this->assertSame('Recherche d\'alternance', $this->last('announcement.deleted')->subject_label);
    }

    public function test_secrets_are_never_stored(): void
    {
        $this->actingAs($this->admin)->post(route('admin.utilisateurs.store'), [
            'name' => 'Nouvel admin', 'username' => 'nouvel', 'email' => 'nouvel@example.com', 'global_role' => 'admin',
            'password' => 'Secret-password-123', 'password_confirmation' => 'Secret-password-123',
        ]);

        $log = $this->last('user.created');
        $this->assertArrayNotHasKey('password', $log->changes);
        $this->assertStringNotContainsString('Secret-password-123', json_encode(AuditLog::all()));
    }

    public function test_article_relations_and_review_steps_are_logged(): void
    {
        $theme = Theme::create(['name' => 'Réseau']);
        $contributor = User::factory()->create(['name' => 'Contributeur']);

        $this->actingAs($contributor)->post(route('admin.articles.store'), [
            'title' => 'Mon article', 'content' => json_encode(['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'x']]]]),
            'themes' => [$theme->id], 'intent' => 'submit',
        ]);
        $article = Article::sole();

        $this->assertSame(['old' => [], 'new' => ['Réseau']], $this->last('article.relations_updated')->changes['thèmes']);
        $this->assertSame('Contributeur', $this->last('article.submitted')->actor_name);

        $this->actingAs($this->admin)->post(route('admin.articles.approve', $article));
        $this->assertSame('Léo', $this->last('article.approved')->actor_name);
    }

    public function test_settings_are_logged_with_readable_labels(): void
    {
        $this->actingAs($this->admin)->put(route('admin.maintenance.update'), ['enabled' => '1']);

        $this->assertTrue(AuditLog::where('subject_label', 'Maintenance : activation')->exists());
        app(Maintenance::class)->disable();
    }

    public function test_public_actions_are_not_logged(): void
    {
        $this->submitVerifiedContact([
            'first_name' => 'Ada', 'last_name' => 'L', 'email' => 'ada@example.com', 'subject' => 'Bonjour', 'message' => 'Un message assez long.', 'consent' => '1',
        ]);

        $this->assertSame(0, AuditLog::where('action', 'like', 'contact_message.%')->count());
    }

    public function test_log_cannot_be_modified_or_deleted(): void
    {
        $this->actingAs($this->admin)->post(route('admin.themes.store'), ['name' => 'Cloud']);
        $log = $this->last('theme.created');

        $this->expectException(LogicException::class);
        $log->update(['actor_name' => 'Falsifié']);
    }

    public function test_only_super_admins_can_view_the_log(): void
    {
        $this->actingAs($this->admin)->post(route('admin.themes.store'), ['name' => 'Cloud']);
        $log = $this->last('theme.created');

        $this->actingAs($this->admin)->get(route('admin.audit.index'))->assertOk()->assertSee('Cloud');
        $this->actingAs($this->admin)->get(route('admin.audit.index', ['category' => 'auth']))->assertOk()->assertDontSee('Cloud');
        $this->actingAs($this->admin)->get(route('admin.audit.show', $log))->assertOk()->assertSee('Cloud');

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_entries_are_archived_after_one_month_and_deleted_after_three(): void
    {
        $entry = fn (string $label, $date) => AuditLog::create(['actor_name' => 'Léo', 'via' => 'web', 'action' => 'theme.created', 'subject_label' => $label, 'created_at' => $date]);
        $entry('Thème Alpha', now()->subDays(10));
        $entry('Thème Beta', now()->subMonths(2));
        $entry('Thème Gamma', now()->subMonths(3)->subDay());

        $this->assertSame(['archived' => 1, 'deleted' => 1], AuditLog::applyRetention());
        $this->assertSame(['Thème Alpha', 'Thème Beta'], AuditLog::orderBy('id')->pluck('subject_label')->all());
        $this->assertNull(AuditLog::where('subject_label', 'Thème Alpha')->value('archived_at'));
        $this->assertSame(['archived' => 0, 'deleted' => 0], AuditLog::applyRetention());

        $this->actingAs($this->admin)->get(route('admin.audit.index'))->assertSee('Thème Alpha')->assertDontSee('Thème Beta');
        $this->actingAs($this->admin)->get(route('admin.audit.index', ['archived' => 'only']))->assertSee('Thème Beta')->assertDontSee('Thème Alpha');
        $this->actingAs($this->admin)->get(route('admin.audit.index', ['archived' => 'all']))->assertSee('Thème Beta')->assertSee('Thème Alpha');
    }

    public function test_super_admin_purges_a_period_and_the_purge_is_traced(): void
    {
        $at = fn (string $date, string $action = 'auth.login') => AuditLog::create(['action' => $action, 'via' => 'web', 'created_at' => $date]);
        $before = $at('2026-09-30 23:59:59');
        $at('2026-10-01 00:00:00');
        $at('2026-10-31 23:59:59');
        $after = $at('2026-11-01 00:00:00');
        $oldPurge = $at('2026-10-15 12:00:00', AuditLog::PURGE_ACTION);

        $this->actingAs($this->admin)->get(route('admin.audit.purge', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()->assertSee('2 entrée(s)', false);

        // Mot de passe et confirmation exigés
        $period = ['from' => '2026-10-01', 'to' => '2026-10-31'];
        $this->delete(route('admin.audit.purge.destroy'), $period + ['password' => 'mauvais', 'confirmation' => 'PURGER'])->assertSessionHasErrors('password');
        $this->delete(route('admin.audit.purge.destroy'), $period + ['password' => 'password', 'confirmation' => 'oui'])->assertSessionHasErrors('confirmation');
        $this->delete(route('admin.audit.purge.destroy'), ['from' => '2026-10-31', 'to' => '2026-10-01', 'password' => 'password', 'confirmation' => 'PURGER'])->assertSessionHasErrors('to');
        $this->assertSame(5, AuditLog::where('action', '!=', 'auth.failed')->count());

        $this->delete(route('admin.audit.purge.destroy'), $period + ['password' => 'password', 'confirmation' => 'PURGER'])
            ->assertRedirect(route('admin.audit.index'))->assertSessionHas('success');

        $this->assertModelExists($before);
        $this->assertModelExists($after);
        $this->assertModelExists($oldPurge);
        $this->assertSame(0, AuditLog::where('action', 'auth.login')->whereBetween('created_at', ['2026-10-01', '2026-10-31 23:59:59'])->count());
        $trace = $this->last(AuditLog::PURGE_ACTION);
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-31', 'deleted' => 2], $trace->meta);
        $this->assertSame($this->admin->id, $trace->user_id);
    }

    public function test_purge_is_reserved_to_super_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.audit.purge'))->assertForbidden();
        $this->delete(route('admin.audit.purge.destroy'), ['from' => '2026-10-01', 'to' => '2026-10-31', 'password' => 'password', 'confirmation' => 'PURGER'])
            ->assertForbidden();
    }

    public function test_purge_command(): void
    {
        AuditLog::create(['action' => 'auth.login', 'via' => 'web', 'created_at' => '2026-10-10 10:00:00']);
        AuditLog::create(['action' => 'auth.login', 'via' => 'web', 'created_at' => '2026-11-02 10:00:00']);

        $this->artisan('portfolio:audit-purge')->assertFailed();
        $this->artisan('portfolio:audit-purge', ['--du' => '2026-10-31', '--au' => '2026-10-01'])->assertFailed();
        $this->artisan('portfolio:audit-purge', ['--du' => '2026-10-01', '--au' => '2026-10-31'])
            ->expectsConfirmation('Supprimer définitivement 1 entrée(s) du 01/10/2026 au 31/10/2026 ?', 'no')->assertSuccessful();
        $this->assertSame(2, AuditLog::where('action', 'auth.login')->count());

        $this->artisan('portfolio:audit-purge', ['--du' => '2026-10-01', '--au' => '2026-10-31', '--force' => true])->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'auth.login')->count());
        $this->assertSame(1, $this->last(AuditLog::PURGE_ACTION)->meta['deleted']);
    }
}
