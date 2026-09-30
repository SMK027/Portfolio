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
        $this->post('/contact', [
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
}
