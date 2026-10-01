<?php

namespace Tests\Feature;

use App\Mail\AppointmentOwnerMail;
use App\Mail\AppointmentVisitorMail;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\AvailabilityClosure;
use App\Models\AvailabilityRule;
use App\Models\Page;
use App\Models\User;
use App\Services\AppointmentSlots;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00')); // lundi
        Page::where('key', 'rendez-vous')->update(['is_public' => true]);
        Page::flushCache();
        AppointmentSettings::save(['duration' => 30, 'notice_hours' => 24, 'horizon_days' => 14]);
        AvailabilityRule::create(['weekday' => 2, 'start_time' => '14:00', 'end_time' => '15:30']); // mardi
    }

    protected function book(string $slot, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('appointments.store'), [
            'slot' => $slot, 'name' => 'Camille Dupont', 'email' => 'camille@example.com', 'topic' => 'Stage', 'consent' => '1', ...$extra,
        ]);
    }

    public function test_slots_respect_rules_notice_closures_and_bookings(): void
    {
        $slots = app(AppointmentSlots::class)->available();
        // Mardi 6 : plus de 24 h → 3 créneaux ; mardi 13 aussi ; rien d'autre.
        $this->assertSame(['2026-10-06', '2026-10-13'], $slots->keys()->all());
        $this->assertSame(['14:00', '14:30', '15:00'], $slots['2026-10-06']->map->format('H:i')->all());

        AvailabilityClosure::create(['date' => '2026-10-13']);
        Appointment::create(['starts_at' => '2026-10-06 14:30', 'ends_at' => '2026-10-06 15:00', 'name' => 'X', 'email' => 'x@x.fr', 'topic' => 'Stage', 'cancel_token' => str_repeat('a', 48)]);

        $slots = app(AppointmentSlots::class)->available();
        $this->assertSame(['2026-10-06'], $slots->keys()->all());
        $this->assertSame(['14:00', '15:00'], $slots['2026-10-06']->map->format('H:i')->all());
    }

    public function test_visitor_books_and_both_parties_are_emailed(): void
    {
        $this->get(route('appointments.show'))->assertOk()->assertSee('14:30');
        $this->book('2026-10-06 14:30')->assertRedirect(route('appointments.show'))->assertSessionHas('success');

        $appointment = Appointment::sole();
        $this->assertSame('pending', $appointment->status);
        $this->assertSame('2026-10-06 15:00', $appointment->ends_at->format('Y-m-d H:i'));
        Mail::assertSent(AppointmentVisitorMail::class, fn ($m) => $m->hasTo('camille@example.com') && $m->event === 'received');
        Mail::assertSent(AppointmentOwnerMail::class, fn ($m) => $m->event === 'requested');
    }

    public function test_taken_or_unoffered_slots_are_refused(): void
    {
        $this->book('2026-10-06 14:30');
        $this->book('2026-10-06 14:30', ['email' => 'autre@example.com'])->assertSessionHasErrors('slot');
        $this->book('2026-10-07 14:30')->assertSessionHasErrors('slot'); // mercredi : pas de plage
        $this->book('2026-10-05 15:00')->assertSessionHasErrors('slot'); // délai de prévenance
        $this->book('2026-10-06 14:00', ['topic' => 'Inconnu'])->assertSessionHasErrors('topic');
        $this->assertSame(1, Appointment::count());
    }

    public function test_visitor_cancels_with_the_emailed_link(): void
    {
        $this->book('2026-10-06 14:30');
        $appointment = Appointment::sole();

        $this->get($appointment->cancelUrl())->assertOk()->assertSee('Annuler le rendez-vous');
        $this->post($appointment->cancelUrl())->assertRedirect();
        $this->assertSame('cancelled', $appointment->fresh()->status);
        Mail::assertSent(AppointmentOwnerMail::class, fn ($m) => $m->event === 'cancelled');
        $this->assertTrue(app(AppointmentSlots::class)->isAvailable(CarbonImmutable::parse('2026-10-06 14:30')));
    }

    public function test_admin_confirms_with_calendar_invitation_or_declines(): void
    {
        $this->book('2026-10-06 14:30');
        $this->book('2026-10-06 15:00', ['email' => 'b@example.com']);
        [$first, $second] = Appointment::orderBy('id')->get()->all();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.appointments.index', ['filtre' => 'en-attente']))->assertOk()->assertSee('Camille Dupont');
        $this->put(route('admin.appointments.decide', $first), ['decision' => 'confirmed', 'admin_note' => 'Lien : https://meet.example/abc'])->assertRedirect();
        $this->assertSame('confirmed', $first->fresh()->status);
        Mail::assertSent(AppointmentVisitorMail::class, function ($m) {
            if ($m->event !== 'confirmed') {
                return false;
            }
            $ics = $m->attachments()[0];

            return $ics->as === 'rendez-vous.ics';
        });
        $this->assertStringContainsString('DTSTART:20261006T123000Z', $first->fresh()->toIcs('Moi'));

        $this->put(route('admin.appointments.decide', $second), ['decision' => 'declined'])->assertRedirect();
        $this->assertSame('declined', $second->fresh()->status);
        $this->put(route('admin.appointments.decide', $second), ['decision' => 'confirmed'])->assertStatus(409);
    }

    public function test_availability_settings_and_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('admin.appointments.availability.update'), [
            'duration' => 60, 'notice_hours' => 0, 'horizon_days' => 7, 'location' => 'Lyon', 'topics' => 'Stage, Café',
            'rules' => [['weekday' => 3, 'start_time' => '10:00', 'end_time' => '12:00']],
            'closures' => [['date' => '2026-10-07', 'reason' => 'Examen']],
        ])->assertRedirect();
        $this->assertSame(['Stage', 'Café'], AppointmentSettings::current()['topics']);
        $this->assertSame([], app(AppointmentSlots::class)->available()->keys()->all()); // seul mercredi, fermé

        $this->put(route('admin.appointments.availability.update'), [
            'duration' => 30, 'notice_hours' => 0, 'horizon_days' => 7, 'location' => 'Lyon', 'topics' => 'Stage',
            'rules' => [['weekday' => 3, 'start_time' => '12:00', 'end_time' => '10:00']],
        ])->assertSessionHasErrors('rules.0.end_time');

        $this->actingAs(User::factory()->create(['global_role' => 'user']))->get(route('admin.appointments.index'))->assertForbidden();
    }

    public function test_private_page_and_honeypot(): void
    {
        $this->book('2026-10-06 14:30', ['website' => 'spam'])->assertRedirect();
        $this->assertSame(0, Appointment::count());

        Page::where('key', 'rendez-vous')->update(['is_public' => false]);
        Page::flushCache();
        $this->get(route('appointments.show'))->assertNotFound();
    }
}
