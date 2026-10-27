<?php

namespace Tests\Feature\Metier;

use App\Enums\NatureLoyer;
use App\Enums\StatutBail;
use App\Enums\StatutBien;
use App\Enums\StatutPaiement;
use App\Enums\StatutReversement;
use App\Models\Depense;
use App\Models\Loyer;
use App\Models\Mandat;
use App\Services\Finance\EncaissementService;
use App\Services\Location\BailService;
use App\Services\Location\ReversementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected BailService $baux;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10');
        $this->creerAgence();
        $this->baux = app(BailService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function bailActif(array $conditions = [])
    {
        $proprietaire = $this->tiers(['nom' => 'BAILLEUR']);
        $mandat = Mandat::create(['numero' => 'MG-1', 'proprietaire_id' => $proprietaire->id, 'date_debut' => '2026-01-01', 'taux_commission' => 10]);
        $bien = $this->bien(['a_louer' => true, 'loyer_mensuel' => 100000, 'proprietaire_id' => $proprietaire->id, 'mandat_id' => $mandat->id]);
        $locataire = $this->tiers(['nom' => 'LOCATAIRE']);

        $bail = $this->baux->creer($bien, $locataire, $conditions + ['date_debut' => '2026-03-01', 'mois_caution' => 2, 'mois_avance' => 2]);

        return [$this->baux->activer($bail), $bien, $locataire, $proprietaire];
    }

    public function test_activation_genere_garanties_frais_et_loyers_d_avance(): void
    {
        [$bail, $bien] = $this->bailActif();

        $this->assertSame(StatutBail::Actif, $bail->statut);
        $this->assertSame(StatutBien::Loue, $bien->refresh()->statut);

        $this->assertSame('200000.00', Loyer::where('nature', NatureLoyer::DepotGarantie->value)->value('montant'));
        // frais d'agence (1 mois) + frais de contrat de bail (obligatoire, 10 000)
        $this->assertSame(110000.0, (float) Loyer::where('nature', NatureLoyer::FraisAgence->value)->sum('montant'));

        // 2 mois d'avance (mars, avril), exigibles à la signature
        $loyers = Loyer::where('nature', NatureLoyer::Loyer->value)->orderBy('periode_debut')->get();
        $this->assertCount(2, $loyers);
        $this->assertTrue($loyers->every(fn ($l) => $l->date_echeance->isSameDay('2026-03-01')));
        $this->assertSame('2026-04-30', $bail->refresh()->loyers_generes_jusqu_au->toDateString());
    }

    public function test_la_generation_mensuelle_est_idempotente(): void
    {
        [$bail] = $this->bailActif();

        $this->assertSame(3, $this->baux->genererLoyers($bail, Carbon::parse('2026-07-15')));
        $this->assertSame(0, $this->baux->genererLoyers($bail->refresh(), Carbon::parse('2026-07-15')));

        $juillet = Loyer::where('nature', 'loyer')->whereDate('periode_debut', '2026-07-01')->firstOrFail();
        $this->assertSame('2026-07-05', $juillet->date_echeance->toDateString()); // jour d'échéance paramétré : 5
    }

    public function test_encaissement_d_un_loyer_delivre_une_quittance(): void
    {
        [$bail, , $locataire] = $this->bailActif();
        $caisse = app(EncaissementService::class);

        $loyer = Loyer::where('nature', 'loyer')->orderBy('periode_debut')->first();
        $caisse->encaisser(['tiers_id' => $locataire->id, 'mode_paiement' => 'MOBILE_MONEY'], [[$loyer, '100000']]);

        $loyer->refresh();
        $this->assertSame(StatutPaiement::Paye, $loyer->statut);
        $this->assertStringStartsWith('QT-', $loyer->quittance_numero);
    }

    public function test_penalite_de_retard_unique_par_loyer(): void
    {
        [$bail] = $this->bailActif(['taux_penalite' => 10]);

        Carbon::setTestNow('2026-05-20');
        $this->baux->genererLoyers($bail->refresh());

        $this->assertGreaterThan(0, $this->baux->appliquerPenalites());
        $this->assertSame(0, $this->baux->appliquerPenalites());
        $this->assertSame('10000.00', Loyer::where('nature', 'penalite')->value('montant'));
    }

    public function test_reversement_au_proprietaire_avec_commission_et_depense(): void
    {
        [$bail, $bien, $locataire, $proprietaire] = $this->bailActif();
        $caisse = app(EncaissementService::class);

        // le locataire paie tout : garanties, frais et 2 loyers
        $caisse->encaisser(['tiers_id' => $locataire->id, 'mode_paiement' => 'ESPECES'],
            $caisse->repartir($caisse->dettesOuvertes($locataire), 510000));

        Depense::create(['numero' => 'DEP-1', 'date_depense' => '2026-03-05', 'categorie' => 'TRAVAUX', 'libelle' => 'Plomberie',
            'montant' => 15000, 'bien_id' => $bien->id, 'refacturable_proprietaire' => true, 'statut' => 'payee']);

        $service = app(ReversementService::class);
        $reversement = $service->preparer($proprietaire, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));

        // seuls les loyers reviennent au propriétaire (pas le dépôt ni les frais d'agence)
        $this->assertSame('200000.00', $reversement->montant_encaisse);
        $this->assertSame('20000.00', $reversement->montant_commission);
        $this->assertSame('15000.00', $reversement->montant_deductions);
        $this->assertSame('165000.00', $reversement->montant_net);

        $service->valider($reversement);
        $this->assertSame(0, Loyer::where('nature', 'loyer')->where('statut', 'paye')->where('reverse', false)->count());

        $service->payer($reversement->refresh(), 'VIREMENT', 'VIR-001');
        $this->assertSame(StatutReversement::Paye, $reversement->refresh()->statut);
    }

    public function test_resiliation_annule_les_loyers_futurs_et_libere_le_bien(): void
    {
        [$bail, $bien] = $this->bailActif();
        $this->baux->genererLoyers($bail, Carbon::parse('2026-08-01'));

        $this->baux->resilier($bail->refresh(), Carbon::parse('2026-05-31'), 'CONGE_LOCATAIRE', 200000);

        $this->assertSame(StatutBien::Disponible, $bien->refresh()->statut);
        $this->assertSame(3, Loyer::where('nature', 'loyer')->where('statut', 'annule')->count()); // juin, juillet, août
        $this->assertSame(0, $this->baux->genererLoyers($bail->refresh(), Carbon::parse('2026-12-01')));
    }
}
