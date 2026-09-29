<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
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

    public function test_message_is_stored_and_notified(): void
    {
        $this->post('/contact', $this->payload())->assertRedirect(route('contact.show'))->assertSessionHas('success');

        $message = ContactMessage::sole();
        $this->assertSame('Ada', $message->first_name);
        $this->assertNotNull($message->consented_at);
        Mail::assertSent(ContactMessageReceived::class);
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
}
