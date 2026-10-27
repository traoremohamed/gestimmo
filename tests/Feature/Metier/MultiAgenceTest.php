<?php

namespace Tests\Feature\Metier;

use App\Models\Bien;
use App\Models\Referentiel;
use App\Support\AgenceCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

class MultiAgenceTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    public function test_les_donnees_sont_isolees_par_agence(): void
    {
        $agenceA = $this->creerAgence('A');
        $bienA = $this->bien(['libelle' => 'Villa agence A']);

        $agenceB = $this->creerAgence('B');
        $this->bien(['libelle' => 'Villa agence B']);

        $this->assertSame(['Villa agence B'], Bien::pluck('libelle')->all());
        $this->assertNull(Bien::find($bienA->id));

        app(AgenceCourante::class)->definir($agenceA);
        $this->assertSame(['Villa agence A'], Bien::pluck('libelle')->all());
        $this->assertSame($agenceA->id, $bienA->agence_id);
        $this->assertSame(2, Bien::toutesAgences()->count());
    }

    public function test_chaque_agence_recoit_ses_listes_de_valeurs(): void
    {
        $this->creerAgence('A');
        $nombreA = Referentiel::count();

        $this->creerAgence('B');
        $this->assertSame($nombreA, Referentiel::count());
        $this->assertSame('Villa', Referentiel::libelle('type_bien', 'VILLA'));
    }

    public function test_creer_sans_agence_courante_est_refuse(): void
    {
        app(AgenceCourante::class)->definir(null);

        $this->expectException(\LogicException::class);
        $this->bien();
    }

    public function test_les_modules_obligatoires_sont_toujours_actifs(): void
    {
        $agence = $this->creerAgence('A', ['vente']);

        $this->assertTrue(app(AgenceCourante::class)->moduleActif('vente'));
        $this->assertTrue(app(AgenceCourante::class)->moduleActif('patrimoine'));
        $this->assertFalse(app(AgenceCourante::class)->moduleActif('syndic'));
    }
}
