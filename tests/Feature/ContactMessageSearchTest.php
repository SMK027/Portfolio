<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactMessageSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function message(array $attributes): ContactMessage
    {
        return ContactMessage::create($attributes + [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'jean@example.com',
            'subject' => 'Bonjour', 'message' => 'Un message.', 'consented_at' => now(),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->message(['subject' => 'Offre de stage']);
        $this->message(['first_name' => 'Claire', 'last_name' => 'Martin', 'email' => 'claire@entreprise.fr', 'subject' => 'Alternance', 'message' => 'Poste en Python à Lyon.']);
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_messages_are_listed_with_a_search_field(): void
    {
        $this->get(route('admin.messages.index'))->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('Offre de stage')->assertSee('Alternance');
    }

    public function test_search_matches_name_email_subject_and_content(): void
    {
        foreach (['claire martin', 'Martin', 'entreprise.fr', 'altern', 'python lyon'] as $term) {
            $this->get(route('admin.messages.index', ['q' => $term]))->assertOk()
                ->assertSee('Alternance')->assertDontSee('Offre de stage')
                ->assertSee('1 message trouvé');
        }
    }

    public function test_every_word_must_match(): void
    {
        $this->get(route('admin.messages.index', ['q' => 'Claire stage']))->assertOk()
            ->assertSee('Aucun message ne correspond');
    }

    public function test_filter_by_read_status(): void
    {
        ContactMessage::where('subject', 'Alternance')->update(['read_at' => now()]);

        $this->get(route('admin.messages.index', ['statut' => 'non-lus']))->assertOk()->assertSee('Offre de stage')->assertDontSee('Alternance');
        $this->get(route('admin.messages.index', ['statut' => 'lus']))->assertOk()->assertSee('Alternance')->assertDontSee('Offre de stage');
        $this->get(route('admin.messages.index', ['statut' => 'autre']))->assertSessionHasErrors('statut');
    }

    public function test_filter_by_period(): void
    {
        ContactMessage::where('subject', 'Alternance')->update(['created_at' => '2026-01-15 10:00:00']);

        $this->get(route('admin.messages.index', ['du' => '2026-01-01', 'au' => '2026-01-31']))->assertOk()
            ->assertSee('Alternance')->assertDontSee('Offre de stage')->assertSee('1 message trouvé');
        $this->get(route('admin.messages.index', ['du' => '2026-01-16']))->assertOk()->assertDontSee('Alternance');
        $this->get(route('admin.messages.index', ['au' => '2026-01-14']))->assertOk()->assertSee('Aucun message ne correspond à ces filtres');
    }

    public function test_message_can_be_marked_as_unread(): void
    {
        $message = ContactMessage::where('subject', 'Alternance')->sole();

        $this->get(route('admin.messages.show', $message))->assertOk()->assertSee('Marquer comme non lu');
        $this->assertNotNull($message->fresh()->read_at);

        $this->patch(route('admin.messages.unread', $message))->assertRedirect(route('admin.messages.index'));
        $this->assertNull($message->fresh()->read_at);
    }
}
