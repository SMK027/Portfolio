<?php

namespace Tests\Feature;

use App\Jobs\SyncAppointmentToGoogle;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\AvailabilityRule;
use App\Models\GoogleBusyPeriod;
use App\Models\User;
use App\Services\AppointmentSlots;
use App\Services\GoogleCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.google_calendar.client_id' => 'client-id', 'services.google_calendar.client_secret' => 'secret']);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00')); // lundi
        AppointmentSettings::save(['duration' => 30, 'notice_hours' => 24, 'horizon_days' => 14]);
        AvailabilityRule::create(['weekday' => 2, 'start_time' => '14:00', 'end_time' => '16:00']); // mardi
        $this->actingAs(User::factory()->admin()->create());
    }

    protected function connect(): void
    {
        app(GoogleCalendar::class)->saveSettings(['token' => Crypt::encryptString('refresh-token'), 'account' => 'moi@gmail.com']);
    }

    protected function fakeGoogle(array $busy = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'expires_in' => 3600, 'refresh_token' => 'refresh-token']),
            'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => [
                ['id' => 'moi@gmail.com', 'summary' => 'Moi', 'primary' => true, 'accessRole' => 'owner'],
            ]]),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => $busy]]]),
            'www.googleapis.com/calendar/v3/calendars/*' => Http::response(['id' => 'evt-1']),
        ]);
    }

    public function test_busy_periods_from_google_hide_the_slots(): void
    {
        $this->connect();
        $this->fakeGoogle([['start' => '2026-10-06T12:00:00Z', 'end' => '2026-10-06T13:00:00Z']]); // 14:00–15:00 à Paris

        $this->assertTrue(app(GoogleCalendar::class)->syncBusy());

        $this->assertSame(1, GoogleBusyPeriod::count());
        $this->assertSame(['15:00', '15:30'], app(AppointmentSlots::class)->available()['2026-10-06']->map->format('H:i')->all());
        $this->getJson(route('admin.appointments.availability.events', ['start' => '2026-10-05', 'end' => '2026-10-12']))
            ->assertJsonFragment(['title' => 'Occupé (Google Agenda)', 'start' => '2026-10-06T14:00:00']);
    }

    public function test_failed_sync_keeps_previous_periods_and_reports_the_error(): void
    {
        $this->connect();
        GoogleBusyPeriod::create(['starts_at' => '2026-10-06 14:00', 'ends_at' => '2026-10-06 15:00']);
        Http::fake(['*' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->assertFalse(app(GoogleCalendar::class)->syncBusy());

        $this->assertSame(1, GoogleBusyPeriod::count());
        $this->assertStringContainsString('reconnectez', app(GoogleCalendar::class)->status()['error']);
    }

    public function test_oauth_callback_checks_state_and_stores_the_encrypted_token(): void
    {
        $this->fakeGoogle();

        $redirect = $this->post(route('admin.appointments.google.connect'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);

        $this->get(route('admin.appointments.google.callback', ['state' => 'falsifié', 'code' => 'abc']))
            ->assertSessionHas('error');
        $this->assertFalse(app(GoogleCalendar::class)->isConnected());

        $this->post(route('admin.appointments.google.connect'));
        $state = session('google-calendar.oauth-state');
        $this->get(route('admin.appointments.google.callback', ['state' => $state, 'code' => 'abc']))
            ->assertRedirect(route('admin.appointments.availability'))->assertSessionHas('success');

        $google = app(GoogleCalendar::class);
        $this->assertTrue($google->isConnected());
        $this->assertSame('moi@gmail.com', $google->settings()['account']);
        $this->assertNotSame('refresh-token', $google->settings()['token']);
    }

    public function test_confirmed_appointment_is_pushed_and_removed_when_cancelled(): void
    {
        $this->connect();
        $this->fakeGoogle();
        $appointment = Appointment::create([
            'starts_at' => '2026-10-06 14:00', 'ends_at' => '2026-10-06 14:30', 'name' => 'Camille Dupont',
            'email' => 'camille@example.com', 'topic' => 'Stage', 'status' => 'pending', 'cancel_token' => Str::random(48),
        ]);
        Http::assertNothingSent();

        $this->put(route('admin.appointments.decide', $appointment), ['decision' => 'confirmed']);
        $this->assertSame('evt-1', $appointment->fresh()->google_event_id);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/calendars/primary/events')
            && $r['attendees'][0]['email'] === 'camille@example.com');

        $this->put(route('admin.appointments.decide', $appointment), ['decision' => 'declined']);
        $this->assertNull($appointment->fresh()->google_event_id);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/evt-1'));
    }

    public function test_nothing_is_queued_when_google_is_not_connected(): void
    {
        Queue::fake();
        Appointment::create([
            'starts_at' => '2026-10-06 14:00', 'ends_at' => '2026-10-06 14:30', 'name' => 'Camille', 'email' => 'c@example.com',
            'topic' => 'Stage', 'status' => 'confirmed', 'cancel_token' => Str::random(48),
        ]);

        Queue::assertNotPushed(SyncAppointmentToGoogle::class);
    }

    public function test_availability_page_lists_google_calendars_and_saves_the_choice(): void
    {
        $this->connect();
        $this->fakeGoogle();

        $this->get(route('admin.appointments.availability'))->assertOk()
            ->assertSee('Connecté à moi@gmail.com')->assertSee('Agendas qui bloquent les créneaux');

        $this->put(route('admin.appointments.google.update'), ['calendars' => ['primary'], 'target' => 'primary'])
            ->assertSessionHas('success');
        $this->assertFalse(app(GoogleCalendar::class)->settings()['push']);
    }

    public function test_disconnect_clears_token_and_busy_periods(): void
    {
        $this->connect();
        Http::fake();
        GoogleBusyPeriod::create(['starts_at' => '2026-10-06 14:00', 'ends_at' => '2026-10-06 15:00']);

        $this->delete(route('admin.appointments.google.disconnect'))->assertSessionHas('success');

        $this->assertFalse(app(GoogleCalendar::class)->isConnected());
        $this->assertSame(0, GoogleBusyPeriod::count());
    }
}
