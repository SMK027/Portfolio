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

        $this->assertSame('Mairie de Lyon', $book->getSheetByName('Contacts')->getCell('A5')->getValue());
        $this->assertSame('Accepté', $book->getSheetByName('Contacts')->getCell('F5')->getValue());
        $this->assertSame('Stage trouvé.', $book->getSheetByName('Remarques')->getCell('B5')->getValue());
        $this->assertSame(1, $book->getSheetByName('Synthèse')->getCell('B5')->getValue());
    }

    public function test_reserved_to_admins_and_authorized_accounts(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.internship.index'))->assertForbidden();
        $this->get(route('admin.internship.export', 'excel'))->assertForbidden();
    }

    public function test_each_action_requires_its_permission(): void
    {
        $contact = InternshipContact::create([
            'company' => 'Atelier Nord', 'address' => '59000 Lille', 'contacted_on' => '2026-10-02',
            'contact_method' => 'call', 'status' => 'pending',
        ]);
        $remark = InternshipRemark::create(['noted_on' => '2026-10-05', 'body' => 'Relancer la semaine prochaine.']);
        $payload = ['company' => 'Atelier Sud', 'address' => '13000 Marseille', 'contacted_on' => '2026-10-02', 'contact_method' => 'call', 'status' => 'pending'];

        // Consultation seule : liste, fiche en lecture seule et exports, aucune modification
        $reader = User::factory()->create(['global_role' => 'staff', 'permissions' => ['internship.read'], 'is_active' => true]);
        $this->actingAs($reader)->get(route('admin.internship.index'))->assertOk()
            ->assertSee('Atelier Nord')->assertSee('Relancer la semaine')
            ->assertDontSee(route('admin.internship.create'))->assertDontSee(route('admin.internship.remarks.store'));
        $this->get(route('admin.internship.edit', $contact))->assertOk()->assertSee('Lecture seule');
        $this->get(route('admin.internship.export', 'pdf'))->assertOk();
        $this->post(route('admin.internship.store'), $payload)->assertForbidden();
        $this->put(route('admin.internship.update', $contact), $payload)->assertForbidden();
        $this->delete(route('admin.internship.destroy', $contact))->assertForbidden();
        $this->post(route('admin.internship.remarks.store'), ['noted_on' => '2026-10-06', 'body' => 'x'])->assertForbidden();
        $this->put(route('admin.internship.remarks.update', $remark), ['noted_on' => '2026-10-06', 'body' => 'x'])->assertForbidden();
        $this->delete(route('admin.internship.remarks.destroy', $remark))->assertForbidden();

        // Sans consultation, pas d'accès à la liste
        $writer = User::factory()->create(['global_role' => 'staff', 'permissions' => ['internship.create'], 'is_active' => true]);
        $this->actingAs($writer)->get(route('admin.internship.index'))->assertForbidden();

        // Habilitations ciblées
        $editor = User::factory()->create(['global_role' => 'staff', 'is_active' => true, 'permissions' => [
            'internship.read', 'internship.update', 'internship_remarks.create', 'internship_remarks.delete',
        ]]);
        $this->actingAs($editor);
        $this->put(route('admin.internship.update', $contact), $payload)->assertRedirect();
        $this->assertSame('Atelier Sud', $contact->fresh()->company);
        $this->post(route('admin.internship.store'), $payload)->assertForbidden();
        $this->delete(route('admin.internship.destroy', $contact))->assertForbidden();
        $this->post(route('admin.internship.remarks.store'), ['noted_on' => '2026-10-06', 'body' => 'Nouvelle piste.'])->assertRedirect();
        $this->put(route('admin.internship.remarks.update', $remark), ['noted_on' => '2026-10-06', 'body' => 'x'])->assertForbidden();
        $this->delete(route('admin.internship.remarks.destroy', $remark))->assertRedirect();
        $this->assertSame(['Nouvelle piste.'], InternshipRemark::pluck('body')->all());
    }

    public function test_list_can_be_searched_and_sorted(): void
    {
        $admin = User::factory()->admin()->create();
        InternshipContact::create(['company' => 'Alpha', 'address' => 'Paris', 'contacted_on' => '2026-10-01', 'contact_method' => 'email', 'status' => 'pending', 'follow_up_on' => today()->subDay()]);
        InternshipContact::create(['company' => 'Bêta', 'address' => 'Lyon', 'contacted_on' => '2026-10-03', 'contact_method' => 'email', 'status' => 'refused']);

        $this->actingAs($admin)->get(route('admin.internship.index', ['q' => 'Lyon']))->assertOk()
            ->assertSee('Bêta')->assertDontSee('Alpha');
        $this->get(route('admin.internship.index', ['tri' => 'entreprise']))->assertOk()
            ->assertSeeInOrder(['Alpha', 'Bêta'])->assertSee('en retard de 1 j');
    }

    public function test_remark_links_are_shortened_and_escaped(): void
    {
        InternshipRemark::create(['noted_on' => '2026-10-13', 'body' => 'Voir (https://www.example.com/offres/recherche?lieux=1&a=2) <b>gras</b>']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.internship.index'))->assertOk()
            ->assertSee('href="https://www.example.com/offres/recherche?lieux=1&amp;a=2"', false)
            ->assertSee('>example.com/offres/recherche</a>)', false)
            ->assertSee('&lt;b&gt;gras&lt;/b&gt;', false);
    }
}
