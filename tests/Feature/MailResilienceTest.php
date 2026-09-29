<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\SafeMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Le site doit rester fonctionnel quelle que soit la panne d'e-mail.
 */
class MailResilienceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{array<string, mixed>}> */
    public static function brokenConfigurations(): array
    {
        return [
            'serveur SMTP injoignable' => [[
                'mail.default'              => 'smtp',
                'mail.mailers.smtp.host'    => '127.0.0.1',
                'mail.mailers.smtp.port'    => 1,
                'mail.mailers.smtp.timeout' => 1,
            ]],
            'mailer inconnu'           => [['mail.default' => 'inexistant']],
            'expéditeur absent'        => [['mail.default' => 'array', 'mail.from.address' => null]],
            'expéditeur invalide'      => [['mail.default' => 'array', 'mail.from.address' => 'pas-une-adresse']],
        ];
    }

    protected function contactPayload(): array
    {
        return [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com',
            'subject' => 'Bonjour', 'message' => 'Un message suffisamment long.', 'consent' => '1',
        ];
    }

    #[DataProvider('brokenConfigurations')]
    public function test_contact_form_still_works_when_mail_is_broken(array $config): void
    {
        config($config);

        $this->post('/contact', $this->contactPayload())
            ->assertRedirect(route('contact.show'))
            ->assertSessionHas('success');

        $message = ContactMessage::sole();
        $this->assertNull($message->notified_at);
        $this->assertNotNull(app(SafeMailer::class)->lastError());
    }

    #[DataProvider('brokenConfigurations')]
    public function test_password_reset_shows_an_error_instead_of_crashing(array $config): void
    {
        $user = User::factory()->create();
        config($config);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    public function test_successful_contact_notification_is_tracked_and_clears_previous_error(): void
    {
        Mail::fake();
        app(SafeMailer::class)->send(null, new ContactMessageReceived(new ContactMessage), 'test');
        $this->assertNotNull(app(SafeMailer::class)->lastError());

        $this->post('/contact', $this->contactPayload())->assertSessionHas('success');

        $this->assertNotNull(ContactMessage::sole()->notified_at);
        $this->assertNull(app(SafeMailer::class)->lastError());
    }

    public function test_admin_is_warned_and_can_see_unsent_notifications(): void
    {
        config(['mail.default' => 'inexistant']);
        $this->post('/contact', $this->contactPayload());
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Les e-mails ne sont pas envoyés')
            ->assertSee('MAIL_MAILER');

        $this->actingAs($admin)->get(route('admin.messages.index'))->assertSee('E-mail non envoyé');
        $this->actingAs($admin)->get(route('admin.messages.show', ContactMessage::sole()))->assertSee('non envoyée');
    }

    public function test_admin_can_send_a_test_email(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.mail.test'))->assertSessionHas('success');
        Mail::assertSent(\App\Mail\TestMail::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_test_email_reports_the_failure(): void
    {
        config(['mail.default' => 'inexistant']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.mail.test'))
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'MAIL_MAILER'));
    }

    public function test_only_admins_can_send_test_emails(): void
    {
        $this->actingAs(User::factory()->create())->post(route('admin.mail.test'))->assertForbidden();
    }
}
