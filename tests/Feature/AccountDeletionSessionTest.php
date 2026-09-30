<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AccountDeletionSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_admin_session_is_rejected_on_next_request(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create(['password' => 'Mot-de-passe-2026!']);

        // Vraie connexion (session + cookie « se souvenir de moi »), pas actingAs.
        $this->post('/login', ['email' => $admin->email, 'password' => 'Mot-de-passe-2026!', 'remember' => 'on']);
        $this->get(route('admin.dashboard'))->assertOk();
        $session = session()->all();

        // Suppression par un super-admin, depuis une autre session.
        Auth::forgetGuards();
        $this->withSession([])->actingAs($super)->delete(route('admin.utilisateurs.destroy', $admin))->assertRedirect();
        $this->assertModelMissing($admin);

        // L'admin supprimé revient avec sa session d'origine.
        Auth::forgetGuards();
        $this->app['auth']->guard()->logout();
        Auth::forgetGuards();
        $this->withSession($session)->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
