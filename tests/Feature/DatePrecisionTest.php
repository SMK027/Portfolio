<?php

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\Diploma;
use App\Models\Education;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatePrecisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_education_with_years_only_and_ongoing(): void
    {
        $this->actingAs($this->admin)->post(route('admin.formations.store'), [
            'title'          => 'BTS SIO',
            'institution'    => 'Lycée',
            'date_precision' => 'year',
            'start_date'     => '2024',
            'end_date'       => '2026',
            'ongoing'        => '1',
        ])->assertSessionHasNoErrors();

        $education = Education::sole();
        $this->assertSame('2024-01-01', $education->start_date->toDateString());
        $this->assertNull($education->end_date);
        $this->assertTrue($education->isOngoing());

        $this->get('/formations')->assertSee('2024 — aujourd')->assertSee('En cours')->assertDontSee('janvier');
    }

    public function test_education_with_month_precision(): void
    {
        $this->actingAs($this->admin)->post(route('admin.formations.store'), [
            'title' => 'Licence', 'institution' => 'Université', 'date_precision' => 'month',
            'start_date' => '2021-09', 'end_date' => '2024-06',
        ])->assertSessionHasNoErrors();

        $this->get('/formations')->assertSee('septembre 2021 — juin 2024');
    }

    public function test_end_cannot_precede_start(): void
    {
        $this->actingAs($this->admin)->post(route('admin.formations.store'), [
            'title' => 'X', 'institution' => 'Y', 'date_precision' => 'year',
            'start_date' => '2024', 'end_date' => '2020',
        ])->assertSessionHasErrors('end_date');
    }

    public function test_format_must_match_precision(): void
    {
        $this->actingAs($this->admin)->post(route('admin.diplomes.store'), [
            'title' => 'Bac', 'institution' => 'Académie', 'date_precision' => 'year', 'obtained_at' => '2024-06-30',
        ])->assertSessionHasErrors('obtained_at');

        $this->actingAs($this->admin)->post(route('admin.diplomes.store'), [
            'title' => 'Bac', 'institution' => 'Académie', 'date_precision' => 'year', 'obtained_at' => '2024',
        ])->assertSessionHasNoErrors();

        $this->assertSame('year', Diploma::sole()->date_precision);
        $this->get('/diplomes')->assertSee('2024');
    }

    public function test_certification_expiring_this_year_is_still_valid(): void
    {
        $this->actingAs($this->admin)->post(route('admin.certifications.store'), [
            'name' => 'Pix', 'date_precision' => 'year',
            'issued_at' => (string) (now()->year - 2), 'expires_at' => (string) now()->year,
        ])->assertSessionHasNoErrors();

        // « Valable jusqu'en <année en cours> » : la période court jusqu'au 31 décembre.
        $this->assertFalse(Certification::sole()->isExpired());
    }

    public function test_forms_prefill_values_in_the_right_format(): void
    {
        $education = Education::create([
            'title' => 'X', 'institution' => 'Y', 'date_precision' => 'month',
            'start_date' => '2022-09-01', 'end_date' => '2024-06-01',
        ]);

        $this->actingAs($this->admin)->get(route('admin.formations.edit', $education))
            ->assertOk()
            ->assertSee('value="2022-09"', false)
            ->assertSee('value="2022"', false)
            ->assertSee('value="2022-09-01"', false);
    }
}
