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
        ])->assertRedirect();
        $this->assertSame(['Stage', 'Café'], AppointmentSettings::current()['topics']);
        $this->get(route('admin.appointments.availability'))->assertOk()->assertSee('data-availability-calendar', false);

        $this->actingAs(User::factory()->create(['global_role' => 'user']))->get(route('admin.appointments.index'))->assertForbidden();
        $this->postJson(route('admin.appointments.availability.ranges.store'), ['kind' => 'date', 'start' => '2026-10-08T10:00', 'end' => '2026-10-08T11:00'])->assertForbidden();
    }

    public function test_calendar_creates_moves_and_deletes_ranges(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        AvailabilityRule::query()->delete();

        // Mercredi 7 : plage hebdomadaire ; jeudi 8 : plage ponctuelle.
        $this->postJson(route('admin.appointments.availability.ranges.store'), ['kind' => 'weekly', 'start' => '2026-10-07T10:00:00+02:00', 'end' => '2026-10-07T11:00:00+02:00'])->assertCreated();
        $this->postJson(route('admin.appointments.availability.ranges.store'), ['kind' => 'date', 'start' => '2026-10-08T16:00', 'end' => '2026-10-08T17:00'])->assertCreated();
        $rule = AvailabilityRule::sole();
        $this->assertSame([3, '10:00'], [$rule->weekday, substr($rule->start_time, 0, 5)]);

        $slots = app(AppointmentSlots::class)->available();
        $this->assertSame(['10:00', '10:30'], $slots['2026-10-07']->map->format('H:i')->all());
        $this->assertSame(['10:00', '10:30'], $slots['2026-10-14']->map->format('H:i')->all()); // chaque semaine
        $this->assertSame(['16:00', '16:30'], $slots['2026-10-08']->map->format('H:i')->all());
        $this->assertArrayNotHasKey('2026-10-15', $slots->all());                              // ponctuelle

        // Flux du calendrier : récurrence, date précise.
        $events = collect($this->getJson(route('admin.appointments.availability.events', ['start' => '2026-10-05T00:00:00', 'end' => '2026-10-12T00:00:00']))->assertOk()->json());
        $this->assertSame([3], $events->firstWhere('id', 'weekly-'.$rule->id)['daysOfWeek']);
        $this->assertSame('2026-10-08T16:00:00', $events->firstWhere('extendedProps.kind', 'date')['start']);

        // Déplacement (glisser) vers vendredi 14 h, puis suppression.
        $this->putJson(route('admin.appointments.availability.ranges.update', ['kind' => 'weekly', 'id' => $rule->id]), ['start' => '2026-10-09T14:00', 'end' => '2026-10-09T15:30'])->assertOk();
        $this->assertSame([5, '14:00', '15:30'], [$rule->fresh()->weekday, substr($rule->fresh()->start_time, 0, 5), substr($rule->fresh()->end_time, 0, 5)]);
        $this->deleteJson(route('admin.appointments.availability.ranges.destroy', ['kind' => 'weekly', 'id' => $rule->id]))->assertOk();
        $this->assertSame(0, AvailabilityRule::count());

        // Plages invalides.
        $this->postJson(route('admin.appointments.availability.ranges.store'), ['kind' => 'date', 'start' => '2026-10-08T17:00', 'end' => '2026-10-08T16:00'])->assertUnprocessable();
        $this->postJson(route('admin.appointments.availability.ranges.store'), ['kind' => 'date', 'start' => '2026-10-08T17:00', 'end' => '2026-10-09T09:00'])->assertUnprocessable();
    }

    public function test_calendar_toggles_closed_days(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson(route('admin.appointments.availability.closures.toggle'), ['date' => '2026-10-06'])->assertJson(['closed' => true]);
        $this->assertArrayNotHasKey('2026-10-06', app(AppointmentSlots::class)->available()->all());
        $this->postJson(route('admin.appointments.availability.closures.toggle'), ['date' => '2026-10-06'])->assertJson(['closed' => false]);
        $this->assertArrayHasKey('2026-10-06', app(AppointmentSlots::class)->available()->all());
        $this->postJson(route('admin.appointments.availability.closures.toggle'), ['date' => '2026-10-01'])->assertUnprocessable(); // passé
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
