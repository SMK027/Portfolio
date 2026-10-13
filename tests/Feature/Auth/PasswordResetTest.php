<?php

namespace Tests\Feature\Auth;

use App\Mail\AccountAccessMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /** Jeton contenu dans le lien de l'e-mail de réinitialisation envoyé à $user. */
    private function requestToken(User $user): string
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => $user->email]);

        $token = null;
        Mail::assertSent(AccountAccessMail::class, function (AccountAccessMail $mail) use ($user, &$token) {
            $token = basename(parse_url($mail->url, PHP_URL_PATH));

            return $mail->hasTo($user->email) && $mail->event === AccountAccessMail::RESET;
        });

        return $token;
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $this->assertNotEmpty($this->requestToken(User::factory()->create()));
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $token = $this->requestToken(User::factory()->create());

        $this->get('/reset-password/'.$token)->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $this->requestToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
    }
}
