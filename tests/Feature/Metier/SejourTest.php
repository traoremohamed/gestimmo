<?php

namespace Tests\Feature\Metier;

use App\Enums\StatutSejour;
use App\Services\Meuble\SejourService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

class SejourTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    public function test_tarification_et_chevauchement(): void
    {
        $this->creerAgence();
        $service = app(SejourService::class);
        $studio = $this->bien(['meuble' => true, 'prix_nuitee' => 25000]);

        $sejour = $service->reserver($studio, $this->tiers(), ['date_arrivee' => '2026-11-01', 'date_depart' => '2026-11-04', 'remise' => 5000]);
        $this->assertSame(3, $sejour->nb_nuits);
        $this->assertSame('70000.00', $sejour->montant);

        // le départ du 4 libère la nuit du 4
        $service->reserver($studio, $this->tiers(), ['date_arrivee' => '2026-11-04', 'date_depart' => '2026-11-05']);

        $service->changerStatut($sejour, StatutSejour::EnCours);
        $this->assertSame(StatutSejour::EnCours, $sejour->refresh()->statut);

        $this->expectException(DomainException::class);
        $service->reserver($studio, $this->tiers(), ['date_arrivee' => '2026-11-02', 'date_depart' => '2026-11-03']);
    }
}
