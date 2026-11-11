<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Mail\ContactVerificationCode;
use App\Models\ContactMessage;
use App\Models\ContactVerification;
use App\Models\AuditLog;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'      => 'Ada',
            'last_name'       => 'Lovelace',
            'email'           => 'ada@example.com',
            'subject'         => 'Proposition de stage',
            'message'         => 'Bonjour, votre profil nous intéresse beaucoup.',
            'consent'         => '1',
            'recaptcha_token' => 'token',
        ], $overrides);
    }

    protected function configureRecaptcha(): void
    {
        config([
            'services.recaptcha.site_key'   => 'site-key',
            'services.recaptcha.secret_key' => 'secret-key',
            'services.recaptcha.min_score'  => 0.5,
        ]);
    }

    public function test_contact_form_is_displayed_with_all_fields(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('name="last_name"', false)
            ->assertSee('name="first_name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="subject"', false)
            ->assertSee('name="message"', false)
            ->assertSee('name="consent"', false);
    }

    public function test_recaptcha_script_is_loaded_when_configured(): void
    {
        $this->configureRecaptcha();

        $this->get('/contact')->assertSee('recaptcha/api.js?render=site-key', false);
    }

    /** Code envoyé par le dernier e-mail de vérification. */
    protected function lastCode(): string
    {
        $code = null;
        Mail::assertSent(ContactVerificationCode::class, function (ContactVerificationCode $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_submission_sends_a_verification_code_without_storing_the_message(): void
    {
        $this->post('/contact', $this->payload())->assertRedirect(route('contact.verification'));

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertSent(ContactVerificationCode::class, fn ($mail) => $mail->hasTo('ada@example.com') && preg_match('/^\d{6}$/', $mail->code));
        Mail::assertNotSent(ContactMessageReceived::class);

        $verification = ContactVerification::sole();
        $this->assertSame('127.0.0.1', $verification->ip_address);
        $this->assertNotSame($this->lastCode(), $verification->code_hash);

        $this->get('/contact/verification')->assertOk()->assertSee('ada@example.com');
    }

    public function test_message_is_stored_with_ip_and_notified_once_code_is_confirmed(): void
    {
        $this->post('/contact', $this->payload(), ['REMOTE_ADDR' => '203.0.113.7']);

        $this->post('/contact/verification', ['code' => $this->lastCode()])
            ->assertRedirect(route('contact.show'))->assertSessionHas('success');

        $message = ContactMessage::sole();
        $this->assertSame('Ada', $message->first_name);
        $this->assertSame('203.0.113.7', $message->ip_address);
        $this->assertNotNull($message->consented_at);
        $this->assertNotNull(ContactVerification::sole()->verified_at);
        Mail::assertSent(ContactMessageReceived::class);

        // Le code ne peut pas resservir.
        $this->post('/contact/verification', ['code' => $this->lastCode()])->assertRedirect(route('contact.show'));
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_wrong_code_is_rejected_and_attempts_are_limited(): void
    {
        $this->post('/contact', $this->payload());
        $good = $this->lastCode();
        $bad = $good === '000000' ? '111111' : '000000';

        for ($i = 0; $i < ContactVerification::MAX_ATTEMPTS; $i++) {
            $this->post('/contact/verification', ['code' => $bad])->assertSessionHasErrors('code');
        }
        $this->post('/contact/verification', ['code' => $good])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->post('/contact', $this->payload());
        $this->travel(ContactVerification::TTL_MINUTES + 1)->minutes();

        $this->post('/contact/verification', ['code' => $this->lastCode()])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_code_can_be_resent_and_previous_one_is_invalidated(): void
    {
        $this->post('/contact', $this->payload());
        $first = $this->lastCode();

        $this->post('/contact/verification/renvoyer')->assertRedirect(route('contact.verification'));
        Mail::assertSent(ContactVerificationCode::class, 2);
        $second = $this->lastCode();

        if ($first !== $second) {
            $this->post('/contact/verification', ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post('/contact/verification', ['code' => $second])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_sender_is_blocked_for_12_hours_after_3_code_emails(): void
    {
        $this->post('/contact', $this->payload());
        $this->post('/contact/verification/renvoyer');
        $this->post('/contact/verification/renvoyer');
        Mail::assertSent(ContactVerificationCode::class, 3);

        // Plus de renvoi, ni de nouveau message, même avec une autre adresse e-mail (même IP)…
        $this->post('/contact/verification/renvoyer')->assertSessionHasErrors('code');
        $this->post('/contact', $this->payload(['email' => 'autre@example.com']))->assertSessionHasErrors('email');
        // … ni depuis une autre IP avec la même adresse e-mail.
        $this->post('/contact', $this->payload(), ['REMOTE_ADDR' => '198.51.100.1'])->assertSessionHasErrors('email');
        Mail::assertSent(ContactVerificationCode::class, 3);
        $this->get('/contact')->assertSee('limite de 3 envois');

        // Le dernier code reçu reste utilisable.
        $this->post('/contact/verification', ['code' => $this->lastCode()])->assertSessionHasNoErrors();

        $this->travel(11)->hours();
        $this->post('/contact', $this->payload())->assertSessionHasErrors('email');

        $this->travel(61)->minutes();
        $this->post('/contact', $this->payload())->assertRedirect(route('contact.verification'));
        Mail::assertSent(ContactVerificationCode::class, 4);
    }

    public function test_failed_code_email_does_not_count(): void
    {
        config(['mail.default' => 'inexistant']);

        $this->post('/contact', $this->payload())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('contact_verifications', 0);
    }

    public function test_verification_page_redirects_without_pending_request(): void
    {
        $this->get('/contact/verification')->assertRedirect(route('contact.show'));
    }

    public function test_consent_is_required(): void
    {
        $this->post('/contact', $this->payload(['consent' => null]))->assertSessionHasErrors('consent');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->post('/contact', [])->assertSessionHasErrors(['first_name', 'last_name', 'email', 'subject', 'message', 'consent']);
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->post('/contact', $this->payload(['website' => 'http://spam.example']))->assertSessionHasErrors('website');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_valid_recaptcha_is_accepted(): void
    {
        $this->configureRecaptcha();
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'contact'])]);

        $this->post('/contact', $this->payload())->assertSessionHasNoErrors();
        $this->post('/contact/verification', ['code' => $this->lastCode()])->assertSessionHasNoErrors();

        $this->assertSame(0.9, ContactMessage::sole()->recaptcha_score);
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'token');
    }

    public function test_low_score_is_rejected(): void
    {
        $this->configureRecaptcha();
        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.1, 'action' => 'contact'])]);

        $this->post('/contact', $this->payload())->assertSessionHasErrors('recaptcha');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_wrong_action_or_failure_is_rejected(): void
    {
        $this->configureRecaptcha();
        Http::fake(['*' => Http::sequence()
            ->push(['success' => true, 'score' => 0.9, 'action' => 'login'])
            ->push(['success' => false])]);

        $this->post('/contact', $this->payload())->assertSessionHasErrors('recaptcha');
        $this->post('/contact', $this->payload())->assertSessionHasErrors('recaptcha');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_missing_token_is_rejected_when_configured(): void
    {
        $this->configureRecaptcha();
        Http::fake();

        $this->post('/contact', $this->payload(['recaptcha_token' => null]))->assertSessionHasErrors('recaptcha');
        Http::assertNothingSent();
    }

    public function test_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->payload());
        }

        $this->post('/contact', $this->payload())->assertStatus(429);
    }

    protected function contactSupervisor(array $permissions = ['contact.skip_verification'], array $extra = []): Supervisor
    {
        $supervisor = new Supervisor(['username' => 'chef', 'user_id' => User::factory()->admin()->create()->id, 'permissions' => $permissions, 'is_active' => true, ...$extra]);
        $supervisor->setPin('4821');
        $supervisor->save();

        return $supervisor;
    }

    public function test_supervisor_can_send_the_message_without_the_code(): void
    {
        $supervisor = $this->contactSupervisor();
        $this->post('/contact', $this->payload(), ['REMOTE_ADDR' => '203.0.113.7']);
        $this->get('/contact/verification')->assertSee('Validation superviseur');

        $this->post('/contact/verification/superviseur', ['username' => 'chef', 'pin' => '4821'])
            ->assertRedirect(route('contact.show'))->assertSessionHas('success');

        $message = ContactMessage::sole();
        $this->assertSame('203.0.113.7', $message->ip_address);
        $this->assertSame($message->id, ContactVerification::sole()->contact_message_id);
        $this->assertNotNull($supervisor->fresh()->last_used_at);
        Mail::assertSent(ContactMessageReceived::class);
        $this->assertSame(1, AuditLog::where('action', 'supervision.granted')->where('subject_id', $supervisor->id)->count());
    }

    public function test_supervisor_bypass_requires_the_dedicated_permission_and_a_valid_pin(): void
    {
        $this->contactSupervisor(['skills.write']);
        $this->post('/contact', $this->payload());

        // Aucun superviseur habilité : formulaire masqué, validation refusée.
        $this->get('/contact/verification')->assertDontSee('Validation superviseur');
        $this->post('/contact/verification/superviseur', ['username' => 'chef', 'pin' => '4821'])->assertSessionHasErrors('pin');

        Supervisor::query()->update(['permissions' => json_encode(['contact.skip_verification'])]);
        $this->post('/contact/verification/superviseur', ['username' => 'chef', 'pin' => '0000'])->assertSessionHasErrors('pin');
        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertSame(2, AuditLog::where('action', 'supervision.failed')->count());
    }

    public function test_inactive_supervisor_cannot_bypass_and_attempts_are_limited(): void
    {
        $this->contactSupervisor(extra: ['is_active' => false]);
        $this->post('/contact', $this->payload());
        $this->post('/contact/verification/superviseur', ['username' => 'chef', 'pin' => '4821'])->assertSessionHasErrors('pin');

        Supervisor::query()->update(['is_active' => true]);
        for ($i = 0; $i < 4; $i++) {
            $this->post('/contact/verification/superviseur', ['username' => 'chef', 'pin' => '0000']);
        }
        $this->post('/contact/verification/superviseur', ['username' => 'chef', 'pin' => '4821'])
            ->assertSessionHasErrors(['pin' => 'Trop de tentatives : réessayez dans 5 minute(s).']);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_supervisor_bypass_needs_a_pending_request(): void
    {
        $this->contactSupervisor();
        $this->post('/contact/verification/superviseur', ['username' => 'chef', 'pin' => '4821'])->assertRedirect(route('contact.show'));
        $this->assertDatabaseCount('contact_messages', 0);
    }
}
