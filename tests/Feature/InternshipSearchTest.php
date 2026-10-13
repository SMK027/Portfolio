<?php

namespace Tests\Feature;

use App\Models\InternshipContact;
use App\Models\InternshipRemark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternshipSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_contacts_and_remarks(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.internship.create'))->assertOk()
            ->assertSee(today()->toDateString());

        $this->post(route('admin.internship.store'), [
            'company' => 'TechSolutions', 'address' => '12 rue des Lilas, 75011 Paris',
            'contacted_on' => '2026-10-01', 'contact_method' => 'email', 'status' => 'pending',
            'follow_up_on' => '2026-10-08', 'follow_up_method' => 'call', 'notes' => 'CV envoyé.',
        ])->assertRedirect(route('admin.internship.index'));

        $contact = InternshipContact::sole();
        $this->assertSame('2026-10-08', $contact->follow_up_on->toDateString());

        // Plus de relance une fois la réponse reçue
        $this->put(route('admin.internship.update', $contact), $contact->only(['company', 'address', 'contact_method']) + [
            'contacted_on' => '2026-10-01', 'status' => 'refused', 'follow_up_on' => '2026-10-08', 'follow_up_method' => 'call',
        ])->assertRedirect();
        $this->assertNull($contact->fresh()->follow_up_on);
        $this->assertNull($contact->fresh()->follow_up_method);

        $this->post(route('admin.internship.remarks.store'), ['noted_on' => '2026-10-10', 'body' => 'Peu de réponses pour l\'instant.'])->assertRedirect();

        $this->get(route('admin.internship.index'))->assertOk()
            ->assertSee('TechSolutions')->assertSee('Refusé')->assertSee('Peu de réponses');

        $this->delete(route('admin.internship.remarks.destroy', InternshipRemark::sole()));
        $this->delete(route('admin.internship.destroy', $contact));
        $this->assertDatabaseCount('internship_contacts', 0);
        $this->assertDatabaseCount('internship_remarks', 0);
    }

    public function test_exports_include_contacts_and_remarks(): void
    {
        $admin = User::factory()->admin()->create();
        InternshipContact::create([
            'company' => 'Mairie de Lyon', 'address' => '69001 Lyon', 'contacted_on' => '2026-10-02',
            'contact_method' => 'in_person', 'status' => 'accepted',
        ]);
        InternshipRemark::create(['noted_on' => '2026-10-05', 'body' => 'Stage trouvé.']);

        $this->actingAs($admin)->get(route('admin.internship.export', 'pdf'))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $response = $this->get(route('admin.internship.export', 'excel'))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $response->streamedContent());
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        unlink($file);

        $this->assertSame('Mairie de Lyon', $book->getSheetByName('Contacts')->getCell('A2')->getValue());
        $this->assertSame('Accepté', $book->getSheetByName('Contacts')->getCell('F2')->getValue());
        $this->assertSame('Stage trouvé.', $book->getSheetByName('Remarques')->getCell('B2')->getValue());
    }

    public function test_reserved_to_admins(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.internship.index'))->assertForbidden();
        $this->get(route('admin.internship.export', 'excel'))->assertForbidden();
    }
}
