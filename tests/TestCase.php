<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les tests n'ont pas besoin des assets compilés par Vite.
        $this->withoutVite();

        // Le cache des pages est statique (durée d'une requête) : on le vide entre les tests.
        \App\Models\Page::flushCache();
        \App\Models\Setting::flushCache();
    }

    /**
     * Message du formulaire de contact dont l'adresse e-mail est confirmée : le code de
     * vérification est créé directement (sans e-mail), puis saisi.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function submitVerifiedContact(array $payload, string $ip = '127.0.0.1'): \Illuminate\Testing\TestResponse
    {
        $verification = \App\Models\ContactVerification::create([
            'email'      => $payload['email'],
            'ip_address' => $ip,
            'code_hash'  => \App\Models\ContactVerification::hashCode('123456'),
            'payload'    => \Illuminate\Support\Arr::only($payload, ['first_name', 'last_name', 'email', 'subject', 'message']),
            'expires_at' => now()->addMinutes(15),
        ]);

        return $this->withSession(['contact_verification_id' => $verification->id])
            ->post(route('contact.verify'), ['code' => '123456']);
    }
}
