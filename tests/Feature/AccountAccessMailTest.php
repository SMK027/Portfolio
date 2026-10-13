<?php

namespace Tests\Feature;

use App\Mail\AccountAccessMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountAccessMailTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Camille Petit', 'username' => 'camille', 'email' => 'camille@example.com', 'global_role' => 'user',
        ];
    }

    public function test_invitation_lets_the_person_choose_a_password(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.utilisateurs.store'), $this->payload(['password_mode' => 'link']))
            ->assertRedirect(route('admin.utilisateurs.index'))->assertSessionHas('success');

        $user = User::where('username', 'camille')->sole();
        $this->assertNull($user->email_verified_at);

        $url = null;
        Mail::assertSent(AccountAccessMail::class, function (AccountAccessMail $mail) use ($user, &$url) {
            $url = $mail->url;

            return $mail->hasTo($user->email) && $mail->event === AccountAccessMail::INVITATION && $mail->expiresIn === 4320
                && ! str_contains($mail->render(), 'Mot de passe :');
        });

        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Enregistrer le mot de passe');

        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->post(route('account.setup.store'), [
            'token' => $token, 'email' => $user->email, 'password' => 'Nouveau-Mot-2026', 'password_confirmation' => 'Nouveau-Mot-2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Nouveau-Mot-2026', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->email_verified_at);

        // Lien à usage unique
        $this->post(route('account.setup.store'), [
            'token' => $token, 'email' => $user->email, 'password' => 'Autre-Mot-2026', 'password_confirmation' => 'Autre-Mot-2026',
        ])->assertSessionHasErrors('email');
    }

    public function test_welcome_mail_is_optional_when_password_is_set(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('admin.utilisateurs.store'), $this->payload([
            'password_mode' => 'set', 'password' => 'Secret-Mot-2026', 'password_confirmation' => 'Secret-Mot-2026', 'notify' => '1',
        ]))->assertSessionHasNoErrors();
        Mail::assertSent(AccountAccessMail::class, fn ($mail) => $mail->event === AccountAccessMail::WELCOME
            && ! str_contains($mail->render(), 'Secret-Mot-2026'));

        $this->post(route('admin.utilisateurs.store'), $this->payload([
            'username' => 'dom', 'email' => 'dom@example.com',
            'password_mode' => 'set', 'password' => 'Secret-Mot-2026', 'password_confirmation' => 'Secret-Mot-2026',
        ]))->assertSessionHasNoErrors();
        Mail::assertSentCount(1);
    }

    public function test_admin_resets_a_password_by_mail_or_sets_it_with_notice(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->superAdmin()->create());
        $user = User::factory()->create(['username' => 'camille', 'email' => 'camille@example.com', 'password' => Hash::make('Ancien-Mot-2026')]);

        $this->put(route('admin.utilisateurs.update', $user), $this->payload(['password_mode' => 'link']))->assertSessionHasNoErrors();
        Mail::assertSent(AccountAccessMail::class, fn ($mail) => $mail->event === AccountAccessMail::RESET && $mail->url);
        $this->assertTrue(Hash::check('Ancien-Mot-2026', $user->fresh()->password)); // l'actuel reste valable

        $this->put(route('admin.utilisateurs.update', $user), $this->payload([
            'password_mode' => 'set', 'password' => 'Nouveau-Mot-2026', 'password_confirmation' => 'Nouveau-Mot-2026', 'notify' => '1',
        ]))->assertSessionHasNoErrors();
        Mail::assertSent(AccountAccessMail::class, fn ($mail) => $mail->event === AccountAccessMail::PASSWORD_CHANGED);
        $this->assertTrue(Hash::check('Nouveau-Mot-2026', $user->fresh()->password));

        // Conserver : ni changement ni e-mail
        $this->put(route('admin.utilisateurs.update', $user), $this->payload(['password_mode' => 'keep', 'notify' => '1']))->assertSessionHasNoErrors();
        Mail::assertSentCount(2);
    }

    public function test_forgot_password_uses_the_same_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'camille@example.com']);

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        Mail::assertSent(AccountAccessMail::class, fn ($mail) => $mail->event === AccountAccessMail::RESET
            && str_contains($mail->url, '/reset-password/') && $mail->expiresIn === 60);
    }
}
