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
    }
}
