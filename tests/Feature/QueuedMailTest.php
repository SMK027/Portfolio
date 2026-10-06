<?php

namespace Tests\Feature;

use App\Jobs\SendMail;
use App\Mail\ContactMessageReceived;
use App\Mail\TestMail;
use App\Models\ContactMessage;
use App\Services\SafeMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class QueuedMailTest extends TestCase
{
    use RefreshDatabase;

    protected function message(): ContactMessage
    {
        return ContactMessage::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'subject' => 'S', 'message' => 'M', 'consented_at' => now()]);
    }

    public function test_mail_is_pushed_to_the_queue_instead_of_being_sent(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        Mail::fake();

        $this->assertTrue(app(SafeMailer::class)->queue('dest@example.com', new TestMail, 'test'));

        Queue::assertPushed(SendMail::class, fn (SendMail $job) => $job->to === 'dest@example.com' && $job->mailable instanceof TestMail);
        Mail::assertNothingSent();
    }

    public function test_invalid_recipient_is_refused_before_queueing(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $this->assertFalse(app(SafeMailer::class)->queue('pas-une-adresse', new TestMail, 'test'));
        Queue::assertNothingPushed();
        $this->assertStringContainsString('destinataire', app(SafeMailer::class)->lastError()['message']);
    }

    public function test_job_sends_the_mail_and_marks_it_as_sent(): void
    {
        Mail::fake();
        $message = $this->message();

        (new SendMail('dest@example.com', new ContactMessageReceived($message), 'contact', $message))->handle(app(SafeMailer::class));

        Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('dest@example.com'));
        $this->assertNotNull($message->fresh()->notified_at);
    }

    public function test_final_failure_is_reported_in_the_administration(): void
    {
        $job = new SendMail('dest@example.com', new TestMail, 'notification de contact');
        $this->assertSame(3, $job->tries);

        $job->failed(new RuntimeException('Connexion SMTP refusée'));

        $this->assertSame('Notification de contact : Connexion SMTP refusée', app(SafeMailer::class)->lastError()['message']);
    }

    public function test_contact_form_queues_the_notification(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        $this->post(route('contact.store'), [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'jean@example.com',
            'subject' => 'Bonjour', 'message' => 'Un message de test suffisamment long.', 'consent' => '1',
        ])->assertSessionHas('success');

        Queue::assertPushed(SendMail::class, fn (SendMail $job) => $job->markSent?->is(ContactMessage::sole()));
        $this->assertNull(ContactMessage::sole()->notified_at); // pas encore parti
    }
}
