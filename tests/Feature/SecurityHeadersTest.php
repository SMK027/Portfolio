<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_api_and_errors_carry_security_headers(): void
    {
        foreach ([$this->get('/'), $this->get('/page-inexistante'), $this->getJson('/api/v1/me')] as $response) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy');
            $csp = $response->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("frame-ancestors 'self'", $csp);
            $this->assertStringContainsString("object-src 'none'", $csp);
            $this->assertStringContainsString('https://www.google.com', $csp); // reCAPTCHA
            $this->assertStringContainsString('https://fonts.bunny.net', $csp);
        }
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_headers_can_be_disabled(): void
    {
        config(['app.security_headers' => false]);
        $this->get('/')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('X-Frame-Options');
    }
}
