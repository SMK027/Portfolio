<?php

namespace Tests\Feature;

use App\Mail\StaffDeactivationMail;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use App\Services\WebAuthn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StaffAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['global_role' => 'staff', 'permissions' => ['projects.read'], 'is_active' => true]);
    }

    protected function payload(array $data = []): array
    {
        return $data + [
            'name' => 'Alice Martin', 'username' => 'alice', 'email' => 'alice@example.com', 'global_role' => 'staff',
            'password' => 'Mot-de-passe-2026!', 'password_confirmation' => 'Mot-de-passe-2026!',
            'is_active' => '1', 'permissions' => ['projects.read', 'messages.read'], 'deactivates_at' => '',
        ];
    }

    public function test_super_admin_creates_staff_with_permissions_and_scheduled_deactivation(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.utilisateurs.create'))->assertOk()->assertSee('Personnel')->assertSee('Désactivation programmée');

        $this->post(route('admin.utilisateurs.store'), $this->payload(['deactivates_at' => now()->addDays(10)->format('Y-m-d\TH:i')]))
            ->assertRedirect(route('admin.utilisateurs.index'));

        $staff = User::where('email', 'alice@example.com')->sole();
        $this->assertTrue($staff->isStaff());
        $this->assertSame(['projects.read', 'messages.read'], $staff->permissions);
        $this->assertTrue($staff->isActive());
        $this->assertTrue($staff->deactivates_at->isFuture());

        $this->get(route('admin.utilisateurs.index'))->assertSee('Personnel')->assertSee("jusqu'au ".$staff->deactivates_at->format('d/m/Y H:i'), false);

        // Sans date : jamais désactivé automatiquement
        $this->put(route('admin.utilisateurs.update', $staff), $this->payload(['password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
        $this->assertNull($staff->fresh()->deactivates_at);

        // Date passée refusée tant que le compte est actif
        $this->put(route('admin.utilisateurs.update', $staff), $this->payload(['password' => '', 'password_confirmation' => '', 'deactivates_at' => now()->subHour()->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('deactivates_at');
    }

    public function test_other_roles_keep_no_permissions_nor_deactivation(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->post(route('admin.utilisateurs.store'), $this->payload(['global_role' => 'user', 'is_active' => '0', 'deactivates_at' => now()->addDay()->format('Y-m-d\TH:i')]));

        $user = User::where('email', 'alice@example.com')->sole();
        $this->assertNull($user->permissions);
        $this->assertNull($user->deactivates_at);
        $this->assertTrue($user->is_active);
    }

    public function test_only_super_admins_manage_staff(): void
    {
        $staff = $this->staff();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.utilisateurs.edit', $staff))->assertForbidden();

        // Personnel autorisé sur les comptes : contributeurs uniquement
        $manager = $this->staff(['permissions' => ['users.read', 'users.write']]);
        $this->actingAs($manager)->get(route('admin.utilisateurs.edit', $staff))->assertForbidden();
        $this->post(route('admin.utilisateurs.store'), $this->payload())->assertSessionHasErrors('global_role');
        $this->post(route('admin.utilisateurs.store'), $this->payload(['global_role' => 'user']))->assertRedirect();
        $this->assertSame('user', User::where('email', 'alice@example.com')->value('global_role'));
    }

    public function test_staff_only_sees_authorized_sections(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff);

        $this->get('/dashboard')->assertRedirect(route('admin.projets.index'));
        $this->get(route('admin.projets.index'))->assertOk()->assertSee('Projets')->assertDontSee(route('admin.messages.index'));
        $this->get(route('admin.messages.index'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.projets.create'))->assertForbidden(); // lecture seule

        // Page « Mon compte » et double authentification, comme tout compte humain
        $this->get(route('profile.edit'))->assertOk();
        $this->assertTrue($staff->can('use-two-factor'));
    }

    public function test_staff_without_permission_sees_the_idle_page(): void
    {
        $this->actingAs($this->staff(['permissions' => []]))->get('/dashboard')->assertRedirect(route('bot.idle'));
        $this->get(route('bot.idle'))->assertOk()->assertSee('Ce compte n\'a encore aucune autorisation');
    }

    public function test_staff_logs_in_with_password_and_security_key(): void
    {
        $staff = $this->staff();

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($staff);
        $this->post('/logout');

        $this->assertTrue($staff->canUsePasswordlessLogin());
        $this->mock(WebAuthn::class, fn ($mock) => $mock->shouldReceive('verifyLogin')->once()->andReturn($staff));
        $this->postJson(route('login.key'), ['credential' => ['id' => 'x']])->assertOk();
        $this->assertAuthenticatedAs($staff);
    }

    public function test_deactivated_or_expired_staff_cannot_log_in(): void
    {
        $inactive = $this->staff(['is_active' => false]);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors(['email' => 'Ce compte est désactivé.']);
        $this->assertGuest();
        $this->assertFalse($inactive->canUsePasswordlessLogin());

        $expired = $this->staff(['deactivates_at' => now()->subMinute()]);
        $this->post('/login', ['email' => $expired->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertFalse($expired->hasPanelPermission('projects.read'));
    }

    public function test_session_is_cut_when_the_deactivation_date_is_reached(): void
    {
        $staff = $this->staff(['deactivates_at' => now()->addHour()]);

        $this->actingAs($staff)->get(route('admin.projets.index'))->assertOk();

        $this->travel(61)->minutes();
        $this->get(route('admin.projets.index'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.session_revoked', 'subject_id' => $staff->id]);
    }

    public function test_scheduled_task_marks_expired_accounts_as_deactivated(): void
    {
        $expired = $this->staff(['deactivates_at' => now()->subMinute()]);
        $later = $this->staff(['deactivates_at' => now()->addDay()]);
        $never = $this->staff();

        $this->assertSame(1, User::deactivateExpired());

        $this->assertFalse($expired->fresh()->is_active);
        $this->assertTrue($later->fresh()->is_active);
        $this->assertTrue($never->fresh()->is_active);
        $this->assertSame(1, AuditLog::where('action', 'user.deactivated')->where('subject_id', $expired->id)->count());
    }

    public function test_staff_is_warned_a_few_days_before_deactivation(): void
    {
        Mail::fake();
        $soon = $this->staff(['deactivates_at' => now()->addDays(2)]);
        $later = $this->staff(['deactivates_at' => now()->addDays(10)]);
        $never = $this->staff();

        $this->assertSame(1, User::warnUpcomingDeactivations());
        $this->assertSame(0, User::warnUpcomingDeactivations()); // une seule fois

        Mail::assertSent(StaffDeactivationMail::class, 1);
        Mail::assertSent(StaffDeactivationMail::class, fn ($mail) => $mail->hasTo($soon->email) && $mail->event === StaffDeactivationMail::WARNING);
        $this->assertStringContainsString('désactivé le', (new StaffDeactivationMail($soon, StaffDeactivationMail::WARNING))->render());

        // Date repoussée puis de nouveau proche : nouvel avertissement
        $soon->refresh()->update(['deactivates_at' => now()->addDays(20)]);
        $this->assertNull($soon->fresh()->deactivation_warned_at);
        $this->travel(18)->days();
        $this->assertSame(1, User::warnUpcomingDeactivations()); // $soon (à J-2) ; la date de $later est déjà passée
        Mail::assertSent(StaffDeactivationMail::class, 2);
    }

    public function test_super_admins_are_notified_on_deactivation_day(): void
    {
        Mail::fake();
        $super = User::factory()->superAdmin()->create();
        User::factory()->admin()->create();
        $expired = $this->staff(['deactivates_at' => now()->subMinute()]);

        User::deactivateExpired();

        Mail::assertSent(StaffDeactivationMail::class, 1);
        Mail::assertSent(StaffDeactivationMail::class, fn ($mail) => $mail->hasTo($super->email) && $mail->event === StaffDeactivationMail::DEACTIVATED && $mail->account->is($expired));
        $this->assertStringContainsString(route('admin.utilisateurs.edit', $expired), (new StaffDeactivationMail($expired, StaffDeactivationMail::DEACTIVATED))->render());
    }

    public function test_warning_can_be_disabled(): void
    {
        Mail::fake();
        config(['auth.staff_warning_days' => 0]);
        $this->staff(['deactivates_at' => now()->addDay()]);

        $this->assertSame(0, User::warnUpcomingDeactivations());
        Mail::assertNothingSent();
    }
}
