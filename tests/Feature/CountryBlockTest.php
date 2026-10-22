<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CountryBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_from_a_blocked_country_are_refused(): void
    {
        CountryBlock::set(['ru', 'CN', 'XX1']);
        $this->assertSame(['CN', 'RU'], CountryBlock::countries());

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
        $this->get('/', ['CF-IPCountry' => 'RU'])->assertForbidden()->assertSee('depuis votre pays');
        $this->getJson('/api/v1/me', ['CF-IPCountry' => 'CN'])->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, 'pays'));
        $this->get('/up', ['CF-IPCountry' => 'RU'])->assertOk();
        $this->get('/', ['CF-IPCountry' => 'FR'])->assertOk();
        $this->get('/')->assertOk(); // pays inconnu

        config(['auth.login_ban.whitelist' => ['203.0.113.0/24']]);
        $this->get('/', ['CF-IPCountry' => 'RU'])->assertOk();
    }

    public function test_super_admin_manages_the_list_but_cannot_block_their_own_country(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeader('CF-IPCountry', 'FR');
        $this->get(route('admin.blocked-countries.edit'))->assertOk()->assertSee('Pays bloqués');

        $this->put(route('admin.blocked-countries.update'), ['countries' => ['RU', 'FR']])->assertSessionHasErrors('countries');
        $this->assertSame([], CountryBlock::countries());

        $this->put(route('admin.blocked-countries.update'), ['countries' => ['RU', 'KP']])->assertSessionHasNoErrors();
        $this->assertSame(['KP', 'RU'], CountryBlock::countries());

        $this->artisan('portfolio:blocked-countries --reset')->assertSuccessful();
        $this->assertSame([], CountryBlock::countries());
    }

    public function test_regular_admins_cannot_access_the_list(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.blocked-countries.edit'));
        $this->assertNotSame(200, $response->status());
    }
}
