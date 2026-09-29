<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Derrière Traefik (HTTPS terminé par le proxy), les URL générées doivent être en https://.
 * Apache (mod_remoteip) transmet à Laravel l'IP publique du visiteur, pas celle du proxy.
 */
class ReverseProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_urls_are_generated_behind_the_proxy_for_public_visitors(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get('/')
            ->assertOk()
            ->assertSee('href="https://', false)
            ->assertDontSee('href="http://', false);
    }

    public function test_plain_http_urls_without_proxy(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="http://[^"]+/formations"#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="https://[^"]+/formations"#', $html);
    }

    public function test_forwarded_host_is_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
            ->get('/')
            ->assertDontSee('evil.example');
    }
}
