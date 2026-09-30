<?php

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuer_is_optional(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.certifications.store'), [
            'name'      => 'Pix',
            'issued_at' => '2024-05-10',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Certification::sole()->issuer);
        $this->get('/certifications')->assertOk()->assertSee('Pix');
    }
}
