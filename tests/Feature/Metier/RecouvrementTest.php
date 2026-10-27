<?php

namespace Tests\Feature\Metier;

use App\Models\Recouvrement;
use App\Models\RegleRelance;
use App\Services\Finance\EncaissementService;
use App\Services\Location\BailService;
use App\Services\Recouvrement\RecouvrementService;
use App\Services\Vente\VenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

class RecouvrementTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dossiers_relances_graduees_promesse_et_solde(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-01-10');
        $this->creerAgence();

        $locataire = $this->tiers(['nom' => 'MAUVAIS', 'email' => 'payeur@test.ci', 'telephone' => '0700000001']);
        $bien = $this->bien(['a_louer' => true, 'loyer_mensuel' => 100000]);
        $baux = app(BailService::class);
        $bail = $baux->activer($baux->creer($bien, $locataire, ['date_debut' => '2026-01-01', 'mois_caution' => 0, 'mois_avance' => 0, 'frais_agence' => 0]));

        // trois mois plus tard : janvier à avril impayés (frais de contrat compris)
        Carbon::setTestNow('2026-04-20');
        $baux->genererLoyers($bail->refresh());

        $service = app(RecouvrementService::class);
        $service->analyser();
        $dossier = Recouvrement::firstOrFail();
        $this->assertSame('location', $dossier->activite);
        $this->assertSame(410000.0, (float) $dossier->montant_du); // 4 loyers + 10 000 de frais de contrat
        $this->assertGreaterThanOrEqual(100, $dossier->joursRetard());

        // le retard (> 60 j) déclenche directement le niveau le plus élevé atteint : mise en demeure (courrier à imprimer)
        $this->assertSame(1, $service->relancesAutomatiques());
        $relance = $dossier->relances()->first();
        $this->assertSame(4, $relance->niveau);
        $this->assertSame('a_imprimer', $relance->statut);
        $this->assertStringContainsString('410 000', $relance->message);
        $this->assertSame('contentieux', $dossier->refresh()->statut);

        // pas de relance automatique en contentieux ni avant le délai
        $this->assertSame(0, $service->relancesAutomatiques());

        // promesse tenue puis paiement total : dossier soldé
        $service->changerStatut($dossier, 'amiable');
        $promesse = $service->promettre($dossier->refresh(), today()->addDays(3), 410000);
        $caisse = app(EncaissementService::class);
        $caisse->encaisser(['tiers_id' => $locataire->id, 'mode_paiement' => 'ESPECES'], $caisse->repartir($caisse->dettesOuvertes($locataire), 410000));

        Carbon::setTestNow('2026-04-25');
        $service->verifierPromesses();
        $this->assertSame('tenue', $promesse->refresh()->statut);

        $service->analyser();
        $this->assertSame('solde', $dossier->refresh()->statut);
    }

    public function test_relance_manuelle_par_sms_et_niveaux_paramtrables(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-01-01');
        $this->creerAgence();
        $acquereur = $this->tiers(['telephone' => '0700000002']);
        $bien = $this->bien(['a_vendre' => true, 'prix_vente' => 1000000]);
        $ventes = app(VenteService::class);
        $vente = $ventes->creer($acquereur);
        $ventes->ajouterBien($vente, $bien);
        $ventes->definirConditions($vente, ['taux_acompte' => 0, 'montant_acompte' => 0, 'nombre_echeances' => 2, 'date_premiere_echeance' => '2026-01-05']);
        $ventes->valider($vente);

        Carbon::setTestNow('2026-01-12'); // 7 jours de retard sur l'échéance 1
        $this->assertSame(4, RegleRelance::count());
        RegleRelance::where('niveau', 1)->update(['delai_jours' => 5]);

        $service = app(RecouvrementService::class);
        $service->analyser();
        $dossier = Recouvrement::where('activite', 'vente')->firstOrFail();
        $this->assertSame(1, $service->relancesAutomatiques());
        $this->assertSame(1, $dossier->refresh()->niveau);
        $this->assertSame('sms', $dossier->relances()->first()->canal);
        $this->assertSame('envoyee', $dossier->relances()->first()->statut);
    }
}
