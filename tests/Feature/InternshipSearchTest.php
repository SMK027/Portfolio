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
            'followed_up_on' => '2026-10-03', 'follow_up_on' => '2026-10-08', 'notes' => 'CV envoyé.',
        ])->assertRedirect(route('admin.internship.index'));

        $contact = InternshipContact::sole();
        $this->assertSame('2026-10-08', $contact->follow_up_on->toDateString());
        $this->assertSame('2026-10-03', $contact->followed_up_on->toDateString());

        // Plus de relance prévue une fois la réponse reçue, la dernière relance est conservée
        $this->put(route('admin.internship.update', $contact), $contact->only(['company', 'address', 'contact_method']) + [
            'contacted_on' => '2026-10-01', 'status' => 'refused', 'followed_up_on' => '2026-10-03', 'follow_up_on' => '2026-10-08',
        ])->assertRedirect();
        $this->assertNull($contact->fresh()->follow_up_on);
        $this->assertSame('2026-10-03', $contact->fresh()->followed_up_on->toDateString());

        // Formulaire web accepté comme moyen de contact ; relance antérieure au contact refusée
        $this->put(route('admin.internship.update', $contact), $contact->only(['company', 'address']) + [
            'contacted_on' => '2026-10-01', 'contact_method' => 'web_form', 'status' => 'refused', 'followed_up_on' => '2026-09-30',
        ])->assertSessionHasErrors('followed_up_on');
        $this->put(route('admin.internship.update', $contact), $contact->only(['company', 'address']) + [
            'contacted_on' => '2026-10-01', 'contact_method' => 'web_form', 'status' => 'refused', 'followed_up_on' => '2026-10-03',
        ])->assertSessionHasNoErrors();
        $this->assertSame('web_form', $contact->fresh()->contact_method);
        $this->put(route('admin.internship.update', $contact), $contact->only(['company', 'address']) + [
            'contacted_on' => '2026-10-01', 'contact_method' => 'other', 'status' => 'refused',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Autre', $contact->fresh()->methodLabel());

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
        // Colonnes choisies : ordre des colonnes conservé, autres colonnes absentes
        $response = $this->get(route('admin.internship.export', ['excel', 'champs' => ['status', 'company', 'inconnu']]))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getSheetByName('Contacts');
        unlink($file);
        $this->assertSame(['Entreprise / organisme', 'Résultat', null], [$sheet->getCell('A4')->getValue(), $sheet->getCell('B4')->getValue(), $sheet->getCell('C4')->getValue()]);
        $this->assertSame(['Mairie de Lyon', 'Accepté'], [$sheet->getCell('A5')->getValue(), $sheet->getCell('B5')->getValue()]);

        $this->get(route('admin.internship.export', ['pdf', 'champs' => ['company', 'followed_up_on']]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('admin.internship.index'))->assertSee('name="champs[]"', false);
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

    public function test_bulk_actions_update_or_delete_selected_contacts(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['address' => 'Paris', 'contacted_on' => '2026-10-01', 'contact_method' => 'email', 'status' => 'pending'];
        $alpha = InternshipContact::create(['company' => 'Alpha', 'follow_up_on' => '2026-10-10'] + $base);
        $beta = InternshipContact::create(['company' => 'Bêta', 'followed_up_on' => '2026-10-04'] + $base);
        $gamma = InternshipContact::create(['company' => 'Gamma'] + $base);

        $this->actingAs($admin)->get(route('admin.internship.index'))->assertOk()->assertSee('bulk-form', false);

        // Résultat appliqué à deux contacts : relance prévue effacée, dernière relance conservée
        $this->post(route('admin.internship.bulk'), [
            'ids' => [$alpha->id, $beta->id], 'action' => 'update', 'status' => 'refused', 'followed_up_on' => '2026-10-12',
        ])->assertSessionHasNoErrors();
        $this->assertSame('refused', $alpha->fresh()->status);
        $this->assertNull($alpha->fresh()->follow_up_on);
        $this->assertSame('2026-10-12', $beta->fresh()->followed_up_on->toDateString());
        $this->assertSame('pending', $gamma->fresh()->status);

        // Effacement d'une date ; relance antérieure au contact refusée pour tout le lot
        $this->post(route('admin.internship.bulk'), ['ids' => [$beta->id], 'action' => 'update', 'clear_followed_up_on' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($beta->fresh()->followed_up_on);
        $this->post(route('admin.internship.bulk'), ['ids' => [$beta->id, $gamma->id], 'action' => 'update', 'followed_up_on' => '2026-09-01'])
            ->assertSessionHasErrors();
        $this->assertNull($gamma->fresh()->followed_up_on);
        $this->post(route('admin.internship.bulk'), ['ids' => [$gamma->id], 'action' => 'update'])->assertSessionHasErrors('bulk');

        $this->post(route('admin.internship.bulk'), ['ids' => [$alpha->id, $gamma->id], 'action' => 'delete'])->assertSessionHasNoErrors();
        $this->assertSame(['Bêta'], InternshipContact::pluck('company')->all());
    }

    public function test_grid_updates_only_submitted_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['address' => 'Paris', 'contacted_on' => '2026-10-01', 'contact_method' => 'email', 'status' => 'pending'];
        $alpha = InternshipContact::create(['company' => 'Alpha'] + $base);
        $beta = InternshipContact::create(['company' => 'Bêta'] + $base);

        $this->actingAs($admin)->get(route('admin.internship.grid'))->assertOk()
            ->assertSee("contacts[{$alpha->id}][company]", false)->assertSee('Bêta')
            ->assertSee('Filtrer toutes les colonnes')->assertSee('x-model="filters.columns[5]"', false);

        $this->put(route('admin.internship.grid.update'), ['contacts' => [
            $alpha->id => ['company' => 'Alpha SA', 'contact_method' => 'other', 'status' => 'processing', 'followed_up_on' => '2026-10-05', 'follow_up_on' => '2026-10-15'] + $base,
        ]])->assertRedirect(route('admin.internship.index'))->assertSessionHasNoErrors();
        $alpha->refresh();
        $this->assertSame(['Alpha SA', 'other', 'processing', '2026-10-05'], [$alpha->company, $alpha->contact_method, $alpha->status, $alpha->followed_up_on->toDateString()]);
        $this->assertSame('Bêta', $beta->fresh()->company);

        // Une ligne invalide bloque tout l'enregistrement
        $this->from(route('admin.internship.grid'))->put(route('admin.internship.grid.update'), ['contacts' => [
            $alpha->id => ['company' => 'Alpha'] + $base,
            $beta->id  => ['company' => ''] + $base,
        ]])->assertRedirect(route('admin.internship.grid'))->assertSessionHasErrors("contacts.{$beta->id}.company");
        $this->assertSame('Alpha SA', $alpha->fresh()->company);
    }

    public function test_no_response_counts_as_a_refusal(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['address' => 'Paris', 'contacted_on' => '2026-10-01', 'contact_method' => 'email'];
        InternshipContact::create(['company' => 'Alpha', 'status' => 'pending'] + $base);
        InternshipContact::create(['company' => 'Bêta', 'status' => 'accepted'] + $base);
        InternshipContact::create(['company' => 'Gamma', 'status' => 'refused'] + $base);

        // Passage en « Sans réponse » : plus de relance prévue, dernière relance conservée
        $this->actingAs($admin)->post(route('admin.internship.store'), [
            'company' => 'Delta', 'status' => 'no_response', 'followed_up_on' => '2026-10-10', 'follow_up_on' => '2026-10-20',
        ] + $base)->assertSessionHasNoErrors();
        $delta = InternshipContact::where('company', 'Delta')->sole();
        $this->assertFalse($delta->isAwaiting());
        $this->assertNull($delta->follow_up_on);
        $this->assertSame('2026-10-10', $delta->followed_up_on->toDateString());

        $summary = app(\App\Services\InternshipSearchExport::class)->summary(InternshipContact::all());
        $this->assertSame(2, $summary['Refusées']);
        $this->assertSame(1, $summary['Réponses attendues']);
        $this->assertSame('75 %', $summary['Taux de réponse']);

        $this->get(route('admin.internship.index', ['statut' => 'no_response']))->assertOk()->assertSee('Delta')->assertDontSee('Gamma');
    }
}
