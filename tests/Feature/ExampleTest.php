<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sans domaine d'agence, la racine mène à la page de connexion.
     */
    public function test_la_racine_redirige_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect(route('connexion'));
        $this->get(route('connexion'))->assertOk();
    }
}
