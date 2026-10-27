<?php

namespace Tests\Feature\Metier;

use App\Models\Compta\Ecriture;
use App\Models\Compta\Ligne;
use App\Models\Depense;
use App\Models\Mandat;
use App\Models\Rh\Employe;
use App\Services\Compta\ComptaService;
use App\Services\Finance\EncaissementService;
use App\Services\Location\BailService;
use App\Services\Location\ReversementService;
use App\Services\Rh\PaieService;
use App\Services\Rh\RhService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

class ComptaPaieTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10');
        $this->creerAgence();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function solde(string $numero): float
    {
        return (float) Ligne::whereHas('compte', fn ($q) => $q->where('numero', $numero))->selectRaw('coalesce(sum(debit) - sum(credit), 0) as s')->value('s');
    }

    public function test_gestion_locative_pour_compte_de_tiers_equilibree(): void
    {
        $proprietaire = $this->tiers(['nom' => 'BAILLEUR']);
        $mandat = Mandat::create(['numero' => 'MG-1', 'proprietaire_id' => $proprietaire->id, 'date_debut' => '2026-01-01', 'taux_commission' => 10]);
        $bien = $this->bien(['a_louer' => true, 'loyer_mensuel' => 100000, 'proprietaire_id' => $proprietaire->id, 'mandat_id' => $mandat->id]);
        $locataire = $this->tiers();
        $baux = app(BailService::class);
        $baux->activer($baux->creer($bien, $locataire, ['date_debut' => '2026-03-01', 'mois_caution' => 2, 'mois_avance' => 1]));

        $caisse = app(EncaissementService::class);
        $recu = $caisse->encaisser(['tiers_id' => $locataire->id, 'mode_paiement' => 'ESPECES'], $caisse->repartir($caisse->dettesOuvertes($locataire), 410000));

        // caisse 410 000 = dépôt 200 000 (165) + frais d'agence 100 000 et contrat 10 000 (7061) + loyer du mandant 100 000 (4671)
        $this->assertSame(410000.0, $this->solde('5711'));
        $this->assertSame(-200000.0, $this->solde('165'));
        $this->assertSame(-110000.0, $this->solde('7061'));
        $this->assertSame(-100000.0, $this->solde('4671'));

        // dépense refacturée au propriétaire puis reversement : le compte du mandant est soldé
        Depense::create(['numero' => 'D1', 'date_depense' => today(), 'categorie' => 'TRAVAUX', 'libelle' => 'Plomberie', 'montant' => 15000,
            'bien_id' => $bien->id, 'refacturable_proprietaire' => true, 'statut' => 'payee', 'mode_paiement' => 'ESPECES']);
        $service = app(ReversementService::class);
        $reversement = $service->preparer($proprietaire, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));
        $service->valider($reversement);
        $service->payer($reversement->refresh(), 'VIREMENT');

        $this->assertSame(0.0, $this->solde('4671'));
        $this->assertSame(-120000.0, $this->solde('7061')); // + commission de 10 000
        $this->assertSame(-75000.0, $this->solde('5211'));  // net reversé par virement

        // toutes les écritures sont équilibrées ; annulation = contre-passation
        $caisse->annuler($recu, 'Erreur de saisie');
        $this->assertTrue(Ecriture::where('nature', 'contrepassation')->exists());
        $this->assertSame(0.0, (float) Ligne::selectRaw('sum(debit) - sum(credit) as s')->value('s'));
    }

    public function test_bulletin_de_paie_ivoirien_et_ecriture_de_paie(): void
    {
        $rh = app(RhService::class);
        $employe = $rh->embaucher(
            ['nom' => 'KONE', 'prenoms' => 'Awa', 'date_embauche' => '2026-01-01', 'situation_familiale' => 'MARIE', 'nombre_enfants' => 2],
            ['type_contrat' => 'CDI', 'salaire_base' => 300000, 'mode_remuneration' => 'FIXE', 'mode_paiement' => 'VIREMENT']
        );
        $this->assertSame('3.0', (string) $employe->nombre_parts); // marié + 2 enfants

        $paie = app(PaieService::class);
        $periode = $paie->periode('2026-03-01');
        $this->assertSame(1, $paie->calculerTout($periode));
        $b = $employe->bulletins()->firstOrFail();

        // brut = 300 000 + transport 30 000 (non imposable) ; CNPS 6,3 % de 300 000 = 18 900 ; CMU 1 000
        // ITS sur 300 000 : (240 000 - 75 000) × 16 % + (300 000 - 240 000) × 21 % = 39 000 ; réduction 3 parts = 22 000 → 17 000
        $this->assertSame('330000.00', (string) $b->brut);
        $this->assertSame(18900.0, (float) collect($b->lignes)->firstWhere('code', 'CNPS_RET_S')['montant']);
        $this->assertSame(17000.0, (float) $b->impots);
        $this->assertSame(330000 - 18900 - 1000 - 17000.0, (float) $b->net_a_payer); // 293 100 (arrondi au multiple de 5)

        $paie->valider($periode->refresh());
        $ecriture = Ecriture::where('operation', 'PAIE')->firstOrFail();
        $this->assertSame((float) $ecriture->lignes()->sum('debit'), (float) $ecriture->lignes()->sum('credit'));
        $this->assertSame(-293100.0, $this->solde('422'));

        $paie->payer($b->refresh(), ['mode_paiement' => 'VIREMENT']);
        $this->assertSame(0.0, $this->solde('422'));
        $this->assertSame('payee', $periode->refresh()->statut);

        $this->expectException(DomainException::class);
        $paie->calculerTout($periode);
    }

    public function test_conges_solde_validation_et_avance_remboursee(): void
    {
        $rh = app(RhService::class);
        $employe = $rh->embaucher(['nom' => 'YAO', 'date_embauche' => '2025-01-01'], ['salaire_base' => 200000]);
        $this->assertSame(1, $rh->acquerirConges(Carbon::parse('2026-02-01')));
        $this->assertSame(0, $rh->acquerirConges(Carbon::parse('2026-02-01'))); // idempotent
        $this->assertSame(2.2, (float) $employe->refresh()->solde_conges);

        $this->expectExceptionMessage('Solde de congés insuffisant');
        $rh->demanderAbsence($employe, 'CONGE_ANNUEL', Carbon::parse('2026-03-16'), Carbon::parse('2026-03-21'));
    }

    public function test_avance_retenue_sur_la_paie(): void
    {
        $rh = app(RhService::class);
        $employe = $rh->embaucher(['nom' => 'BAMBA', 'date_embauche' => '2025-06-01'], ['salaire_base' => 200000]);
        $avance = $rh->accorderAvance($employe, ['montant' => 60000, 'nombre_echeances' => 3, 'mode_paiement' => 'ESPECES']);
        $this->assertSame(60000.0, $this->solde('421'));

        $paie = app(PaieService::class);
        $periode = $paie->periode('2026-03-01');
        $paie->calculerTout($periode);
        $this->assertSame(20000.0, (float) collect($employe->bulletins()->first()->lignes)->firstWhere('code', 'AVANCE')['montant']);

        $paie->valider($periode->refresh());
        $this->assertSame('20000.00', (string) $avance->refresh()->montant_rembourse);
        $this->assertSame(40000.0, $this->solde('421'));
    }

    public function test_cloture_d_exercice(): void
    {
        $locataire = $this->tiers();
        $bien = $this->bien(['a_louer' => true, 'loyer_mensuel' => 50000]);
        $baux = app(BailService::class);
        $baux->activer($baux->creer($bien, $locataire, ['date_debut' => '2026-03-01', 'mois_caution' => 0, 'mois_avance' => 1, 'frais_agence' => 0]));
        $caisse = app(EncaissementService::class);
        $caisse->encaisser(['tiers_id' => $locataire->id, 'mode_paiement' => 'ESPECES'], $caisse->repartir($caisse->dettesOuvertes($locataire), 60000));

        $compta = app(ComptaService::class);
        $etats = $compta->etatsFinanciers('2026-01-01', '2026-12-31');
        $this->assertSame(60000.0, $etats['resultat']); // loyer d'un bien propre 50 000 + frais de contrat 10 000

        $exercice = $compta->exercice('2026-03-10');
        $this->assertSame(60000.0, $compta->cloturer($exercice)['resultat']);
        $this->assertSame(-60000.0, $this->solde('131'));

        $this->expectException(DomainException::class);
        $compta->exercice('2026-05-01');
    }
}
