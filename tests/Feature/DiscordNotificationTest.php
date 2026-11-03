<?php

namespace Tests\Feature;

use App\Jobs\SendDiscordNotification;
use App\Models\CvAccessCode;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\User;
use App\Services\DiscordNotifier;
use App\Services\LoginBan;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DiscordNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected const WEBHOOK = 'https://discord.com/api/webhooks/123456789/abcDEF-ghi_jkl';

    protected function configure(array $events): void
    {
        app(DiscordNotifier::class)->save(self::WEBHOOK, $events);
    }

    public function test_super_admin_configures_the_webhook_which_is_stored_encrypted(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.notifications.edit'))->assertOk()->assertSee('Aucun webhook');
        $this->actingAs($admin)->put(route('admin.notifications.update'), [
            'webhook' => self::WEBHOOK, 'events' => ['contact', 'ip_ban'],
        ])->assertSessionHasNoErrors();

        $stored = Setting::get(DiscordNotifier::SETTING);
        $this->assertStringNotContainsString('discord.com', json_encode($stored));
        $this->assertSame(['webhook' => self::WEBHOOK, 'events' => ['contact', 'ip_ban']], app(DiscordNotifier::class)->settings());

        // Champ vide : l'adresse est conservée ; la page n'affiche que sa fin.
        $this->actingAs($admin)->put(route('admin.notifications.update'), ['events' => ['contact']])->assertSessionHasNoErrors();
        $this->assertSame(self::WEBHOOK, app(DiscordNotifier::class)->settings()['webhook']);
        $this->actingAs($admin)->get(route('admin.notifications.edit'))->assertSee('…/hi_jkl')->assertDontSee(self::WEBHOOK);

        $this->actingAs($admin)->put(route('admin.notifications.update'), ['forget' => '1'])->assertSessionHasNoErrors();
        $this->assertFalse(app(DiscordNotifier::class)->isConfigured());
    }

    public function test_only_discord_webhooks_are_accepted_and_only_super_admins(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->put(route('admin.notifications.update'), ['webhook' => 'http://169.254.169.254/latest'])
            ->assertSessionHasErrors('webhook');

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.notifications.edit'))->assertForbidden();
    }

    public function test_test_message_is_sent_immediately(): void
    {
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        $this->configure([]);

        $this->actingAs(User::factory()->superAdmin()->create())->post(route('admin.notifications.test'))->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::WEBHOOK && $r['embeds'][0]['title'] === 'Notification de test');
    }

    public function test_contact_message_is_notified_when_the_event_is_enabled(): void
    {
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        $this->configure(['contact']);

        $this->post(route('contact.store'), [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'jean@example.com',
            'subject' => 'Proposition de stage', 'message' => 'Bonjour @everyone, un message suffisamment long.', 'consent' => '1',
        ])->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $r) => $r['embeds'][0]['title'] === 'Proposition de stage'
            && $r['allowed_mentions'] === ['parse' => []]
            && collect($r['embeds'][0]['fields'])->contains('value', 'jean@example.com'));
    }

    public function test_disabled_events_and_missing_webhook_send_nothing(): void
    {
        Queue::fake();

        app(DiscordNotifier::class)->notify('contact', 'Sans webhook');
        $this->configure(['ip_ban']);
        app(DiscordNotifier::class)->notify('contact', 'Événement désactivé');

        Queue::assertNothingPushed();
    }

    public function test_queued_job_does_not_carry_the_webhook(): void
    {
        Queue::fake();
        $this->configure(['ip_ban']);

        app(LoginBan::class)->banManually('203.0.113.50', null, 'Spam', User::factory()->superAdmin()->create());

        Queue::assertPushed(SendDiscordNotification::class, fn (SendDiscordNotification $job) => $job->webhook === null
            && str_contains($job->payload['embeds'][0]['title'], '203.0.113.50')
            && ! str_contains(serialize($job), 'discord.com'));
    }

    public function test_cv_download_with_a_code_is_notified(): void
    {
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        Storage::fake('local');
        Storage::disk('local')->put('cv/cv.pdf', '%PDF-1.4');
        Profile::current()->forceFill(['cv_path' => 'cv/cv.pdf', 'cv_access' => 'restricted'])->save();
        $code = CvAccessCode::create(['label' => 'Société X', 'code' => CvAccessCode::generate()]);
        $this->configure(['cv_download']);

        $this->post(route('cv.download.submit'), ['code' => $code->formatted()])->assertOk();

        Http::assertSent(fn (HttpRequest $r) => $r['embeds'][0]['title'] === 'CV téléchargé : Société X');
    }

    public function test_failed_delivery_never_breaks_the_visitor_action(): void
    {
        Http::fake(['discord.com/*' => Http::response('rate limited', 429)]);
        $this->configure(['contact']);

        $this->post(route('contact.store'), [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'jean@example.com',
            'subject' => 'Bonjour', 'message' => 'Un message de test suffisamment long.', 'consent' => '1',
        ])->assertSessionHas('success');
    }
}
