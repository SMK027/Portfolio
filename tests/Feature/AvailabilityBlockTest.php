<?php

namespace Tests\Feature;

use App\Mail\AppointmentVisitorMail;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityRule;
use App\Models\User;
use App\Services\AppointmentSlots;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilityBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00')); // lundi
        AppointmentSettings::save(['duration' => 30, 'notice_hours' => 24, 'horizon_days' => 14]);
        AvailabilityRule::create(['weekday' => 2, 'start_time' => '14:00', 'end_time' => '16:00']); // mardi
        $this->actingAs(User::factory()->admin()->create());
    }

    protected function appointment(string $start, string $status = 'confirmed', string $email = 'camille@example.com'): Appointment
    {
        return Appointment::create([
            'starts_at' => $start, 'ends_at' => CarbonImmutable::parse($start)->addMinutes(30),
            'name' => 'Camille Dupont', 'email' => $email, 'topic' => 'Stage', 'status' => $status, 'cancel_token' => Str::random(48),
        ]);
    }

    protected function block(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('admin.appointments.availability.blocks.store'), $data + [
            'start' => '2026-10-06T14:00:00', 'end' => '2026-10-06T15:00:00', 'type' => 'appointment', 'channel' => 'phone', 'note' => 'Mme Durand',
        ]);
    }

    public function test_block_without_conflict_is_created_and_hides_the_slots(): void
    {
        $this->block()->assertCreated()->assertJsonPath('message', 'Horaire bloqué.');

        $block = AvailabilityBlock::sole();
        $this->assertSame('phone', $block->channel);
        $this->assertSame('RDV (appel téléphonique) — Mme Durand', $block->title());
        $this->assertSame(['15:00', '15:30'], app(AppointmentSlots::class)->available()['2026-10-06']->map->format('H:i')->all());
        Mail::assertNothingSent();

        // Visible dans le calendrier, et l'horaire ne peut plus être réservé
        $this->getJson(route('admin.appointments.availability.events', ['start' => '2026-10-05', 'end' => '2026-10-12']))
            ->assertJsonFragment(['id' => 'block-'.$block->id, 'title' => $block->title()]);
    }

    public function test_last_minute_change_needs_no_channel(): void
    {
        $this->block(['type' => 'change', 'channel' => null, 'note' => null])->assertCreated();
        $this->assertNull(AvailabilityBlock::sole()->channel);
        $this->assertSame('Indisponible', AvailabilityBlock::sole()->title());

        $this->block(['type' => 'appointment', 'channel' => null])->assertJsonValidationErrors('channel');
        $this->block(['start' => '2026-10-04T10:00:00', 'end' => '2026-10-04T11:00:00'])->assertJsonValidationErrors('end'); // passé
        $this->block(['end' => '2026-10-07T10:00:00'])->assertJsonValidationErrors('end'); // sur deux jours
    }

    public function test_conflicting_appointments_require_confirmation_then_are_cancelled_and_notified(): void
    {
        $confirmed = $this->appointment('2026-10-06 14:30');
        $pending = $this->appointment('2026-10-06 14:00', 'pending', 'alex@example.com');
        $outside = $this->appointment('2026-10-06 15:30', 'confirmed', 'hors@example.com');

        // Sans confirmation : rien n'est créé, la liste des rendez-vous concernés est renvoyée
        $this->block()->assertStatus(409)
            ->assertJsonCount(2, 'conflicts')
            ->assertJsonPath('conflicts.0.email', 'alex@example.com')
            ->assertJsonPath('conflicts.1.email', 'camille@example.com');
        $this->assertSame(0, AvailabilityBlock::count());
        $this->assertSame('confirmed', $confirmed->fresh()->status);
        Mail::assertNothingSent();

        // Confirmé : blocage créé, rendez-vous annulés, visiteurs prévenus
        $this->block(['confirm' => true, 'message' => 'Toutes mes excuses.'])->assertCreated()
            ->assertJsonPath('message', 'Horaire bloqué. 2 rendez-vous annulés, 2 visiteurs prévenus par e-mail.');

        $this->assertSame(1, AvailabilityBlock::count());
        $this->assertSame('cancelled', $confirmed->fresh()->status);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('Toutes mes excuses.', $confirmed->fresh()->admin_note);
        $this->assertSame('confirmed', $outside->fresh()->status);

        Mail::assertSent(AppointmentVisitorMail::class, 2);
        Mail::assertSent(AppointmentVisitorMail::class, fn ($mail) => $mail->hasTo('camille@example.com') && $mail->event === AppointmentVisitorMail::RESCHEDULE);
        Mail::assertNotSent(AppointmentVisitorMail::class, fn ($mail) => $mail->hasTo('hors@example.com'));
    }

    public function test_reschedule_email_invites_to_book_again(): void
    {
        $appointment = $this->appointment('2026-10-06 14:30');
        $appointment->admin_note = 'Toutes mes excuses.';

        $html = (new AppointmentVisitorMail($appointment, AppointmentVisitorMail::RESCHEDULE))->render();

        $this->assertStringContainsString('Votre rendez-vous doit être déplacé', $html);
        $this->assertStringContainsString('Toutes mes excuses.', $html);
        $this->assertStringContainsString('Réserver un nouveau créneau', $html);
        $this->assertStringContainsString(route('appointments.show'), $html);
        $this->assertStringContainsString('annulé', (new AppointmentVisitorMail($appointment, AppointmentVisitorMail::RESCHEDULE))->envelope()->subject);
    }

    public function test_block_is_deleted_and_slots_are_offered_again(): void
    {
        $this->block()->assertCreated();
        $block = AvailabilityBlock::sole();

        $this->deleteJson(route('admin.appointments.availability.blocks.destroy', $block))->assertOk();
        $this->assertSame(0, AvailabilityBlock::count());
        $this->assertCount(4, app(AppointmentSlots::class)->available()['2026-10-06']);
    }

    public function test_writing_permission_is_required(): void
    {
        $this->actingAs(User::factory()->create(['global_role' => 'user']));
        $this->block()->assertForbidden();
        $this->assertSame(0, AvailabilityBlock::count());
    }
}
