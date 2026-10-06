<?php

namespace Tests\Feature;

use App\Mail\AppointmentVisitorMail;
use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\User;
use App\Services\AppointmentReminders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    }

    protected function appointment(string $start, string $status = 'confirmed'): Appointment
    {
        return Appointment::create([
            'starts_at' => $start, 'ends_at' => CarbonImmutable::parse($start)->addMinutes(30),
            'name' => 'Camille', 'email' => Str::random(6).'@example.com', 'topic' => 'Stage', 'status' => $status,
            'cancel_token' => Str::random(48), 'admin_note' => 'Lien : https://visio.example.com/abc',
        ]);
    }

    protected function remind(): int
    {
        return app(AppointmentReminders::class)->send();
    }

    public function test_day_then_hour_reminders_are_sent_once(): void
    {
        $appointment = $this->appointment('2026-10-06 08:30'); // demain, dans 23 h 30

        $this->assertSame(1, $this->remind());
        Mail::assertSent(AppointmentVisitorMail::class, fn ($mail) => $mail->event === AppointmentVisitorMail::REMINDER_DAY && $mail->hasTo($appointment->email));
        $this->assertSame(0, $this->remind()); // une seule fois

        $this->travelTo(CarbonImmutable::parse('2026-10-06 07:45'));
        $this->assertSame(1, $this->remind());
        Mail::assertSent(AppointmentVisitorMail::class, fn ($mail) => $mail->event === AppointmentVisitorMail::REMINDER_HOUR);
        $this->assertSame(0, $this->remind());

        Mail::assertSent(AppointmentVisitorMail::class, 2);
    }

    public function test_only_confirmed_upcoming_appointments_are_reminded(): void
    {
        $this->appointment('2026-10-05 20:00', 'pending');
        $this->appointment('2026-10-05 20:00', 'cancelled');
        $this->appointment('2026-10-05 08:00'); // passé
        $this->appointment('2026-10-08 10:00'); // trop tôt pour un rappel

        $this->assertSame(0, $this->remind());
        Mail::assertNothingSent();
    }

    public function test_appointment_within_the_hour_only_gets_the_hour_reminder(): void
    {
        $appointment = $this->appointment('2026-10-05 09:40');

        $this->assertSame(1, $this->remind());
        Mail::assertSent(AppointmentVisitorMail::class, 1);
        Mail::assertSent(AppointmentVisitorMail::class, fn ($mail) => $mail->event === AppointmentVisitorMail::REMINDER_HOUR);
        $this->assertNotNull($appointment->fresh()->day_reminder_sent_at);
    }

    public function test_late_confirmation_replaces_the_reminders(): void
    {
        $appointment = $this->appointment('2026-10-05 15:00', 'pending');
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.appointments.decide', $appointment), ['decision' => 'confirmed']);

        Mail::assertSent(AppointmentVisitorMail::class, fn ($mail) => $mail->event === AppointmentVisitorMail::CONFIRMED);
        $this->assertSame(0, $this->remind()); // la confirmation (6 h avant) tient lieu de rappel de la veille

        $this->travelTo(CarbonImmutable::parse('2026-10-05 14:10'));
        $this->assertSame(1, $this->remind()); // mais le rappel « 1 h » part bien
    }

    public function test_reminders_can_be_disabled(): void
    {
        AppointmentSettings::save(['reminder_day' => false, 'reminder_hour' => false]);
        $this->appointment('2026-10-05 09:40');
        $this->appointment('2026-10-06 08:30');

        $this->assertSame(0, $this->remind());
        Mail::assertNothingSent();
    }

    public function test_reminder_email_content(): void
    {
        $appointment = $this->appointment('2026-10-06 08:30');
        $mail = new AppointmentVisitorMail($appointment, AppointmentVisitorMail::REMINDER_DAY);

        $this->assertSame('Rappel : rendez-vous demain à 08:30', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('Rappel de votre rendez-vous', $html);
        $this->assertStringContainsString('https://visio.example.com/abc', $html);
        $this->assertStringContainsString($appointment->cancelUrl(), $html);
        $this->assertCount(1, $mail->attachments());

        $this->assertStringContainsString('dans moins d', (new AppointmentVisitorMail($appointment, AppointmentVisitorMail::REMINDER_HOUR))->envelope()->subject);
    }

    public function test_settings_page_toggles_reminders(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.appointments.availability.update'), [
                'duration' => 30, 'notice_hours' => 24, 'horizon_days' => 30, 'location' => 'Visio', 'topics' => 'Stage',
                'reminder_day' => '1', 'reminder_hour' => '0',
            ])->assertSessionHasNoErrors();

        $this->assertTrue(AppointmentSettings::current()['reminder_day']);
        $this->assertFalse(AppointmentSettings::current()['reminder_hour']);
    }
}
