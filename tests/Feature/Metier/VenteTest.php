<?php

namespace Tests\Feature\Metier;

use App\Enums\StatutBien;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\StatutVente;
use App\Models\FraisType;
use App\Services\Finance\EncaissementService;
use App\Services\Vente\ReservationService;
use App\Services\Vente\VenteService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

class VenteTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected VenteService $ventes;

    protected EncaissementService $caisse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerAgence();
        $this->ventes = app(VenteService::class);
        $this->caisse = app(EncaissementService::class);
    }

    protected function venteValidee(string $prix = '10000000', int $echeances = 4)
    {
        $acquereur = $this->tiers();
        $bien = $this->bien(['a_vendre' => true, 'prix_vente' => $prix]);

        $vente = $this->ventes->creer($acquereur);
        $this->ventes->ajouterBien($vente, $bien);
        $this->ventes->definirConditions($vente, ['taux_acompte' => 30, 'nombre_echeances' => $echeances, 'periodicite_mois' => 1]);

        return [$this->ventes->valider($vente), $bien, $acquereur];
    }

    public function test_la_validation_genere_un_echeancier_exact_et_vend_le_bien(): void
    {
        [$vente, $bien] = $this->venteValidee('10000000', 3);

        $this->assertSame(StatutVente::EnCours, $vente->statut);
        $this->assertStringStartsWith('CV-' . date('Y') . '-', $vente->numero);
        $this->assertSame(StatutBien::Vendu, $bien->refresh()->statut);

        $echeances = $vente->echeances()->orderBy('numero')->get();
        $this->assertCount(4, $echeances); // acompte + 3
        $this->assertSame('3000000.00', $echeances[0]->montant);
        $this->assertSame('10000000.00', number_format($echeances->sum('montant'), 2, '.', ''));

        // frais obligatoire (ouverture de dossier) ajouté automatiquement
        $this->assertSame('200000.00', $vente->montant_frais);
    }

    public function test_un_bien_reserve_par_un_autre_client_ne_peut_pas_etre_vendu(): void
    {
        $bien = $this->bien(['a_vendre' => true, 'prix_vente' => 5000000]);
        app(ReservationService::class)->reserver($this->tiers(), $bien);

        $vente = $this->ventes->creer($this->tiers());

        $this->expectException(DomainException::class);
        $this->ventes->ajouterBien($vente, $bien->refresh());
    }

    public function test_une_reservation_convertie_bloque_puis_vend_le_bien(): void
    {
        $client = $this->tiers();
        $bien = $this->bien(['a_vendre' => true, 'prix_vente' => 5000000]);
        $reservation = app(ReservationService::class)->reserver($client, $bien);
        $this->assertSame(StatutBien::Reserve, $bien->refresh()->statut);

        $vente = $this->ventes->creerDepuisReservation($reservation);
        $this->ventes->valider($vente);

        $this->assertSame(StatutReservation::Convertie, $reservation->refresh()->statut);
        $this->assertSame(StatutBien::Vendu, $bien->refresh()->statut);
    }

    public function test_les_reservations_expirees_liberent_le_bien(): void
    {
        $bien = $this->bien(['a_vendre' => true, 'prix_vente' => 5000000]);
        $reservation = app(ReservationService::class)->reserver($this->tiers(), $bien, null, 3);
        $reservation->update(['date_expiration' => today()->subDay()]);

        $this->assertSame(1, app(ReservationService::class)->expirer());
        $this->assertSame(StatutBien::Disponible, $bien->refresh()->statut);
    }

    public function test_encaissement_reparti_puis_solde_puis_annule(): void
    {
        [$vente, , $acquereur] = $this->venteValidee('1000000', 2);

        $dettes = $this->caisse->dettesOuvertes($acquereur);
        $this->assertCount(4, $dettes); // frais + acompte + 2 échéances

        // 500 000 : frais 200 000 + acompte 300 000
        $recu = $this->caisse->encaisser(['tiers_id' => $acquereur->id, 'mode_paiement' => 'ESPECES'], $this->caisse->repartir($dettes, 500000));
        $this->assertSame('500000.00', $recu->montant);
        $this->assertSame(StatutPaiement::Paye, $vente->echeances()->where('numero', 0)->first()->statut);

        // solde
        $this->caisse->encaisser(['mode_paiement' => 'VIREMENT'], $this->caisse->repartir($this->caisse->dettesOuvertes($acquereur), 700000));
        $this->assertSame(StatutVente::Soldee, $vente->refresh()->statut);

        // annulation du premier reçu : le contrat repasse en cours
        $this->caisse->annuler($recu, 'Chèque impayé');
        $this->assertSame(StatutVente::EnCours, $vente->refresh()->statut);
        $this->assertSame('0.00', $vente->echeances()->where('numero', 0)->first()->montant_paye);
    }

    public function test_un_trop_percu_est_refuse(): void
    {
        [, , $acquereur] = $this->venteValidee('1000000', 1);
        $dette = $this->caisse->dettesOuvertes($acquereur)->first();

        $this->expectException(DomainException::class);
        $this->caisse->encaisser(['mode_paiement' => 'ESPECES'], [[$dette, '999999999']]);
    }

    public function test_desistement_avec_retenue_et_remboursement(): void
    {
        [$vente, $bien, $acquereur] = $this->venteValidee('1000000', 2);
        FraisType::query()->update(['montant' => 0]);

        // acompte 300 000 versé
        $acompte = $vente->echeances()->where('numero', 0)->first();
        $this->caisse->encaisser(['mode_paiement' => 'ESPECES'], [[$acompte, '300000']]);

        $vente = $this->ventes->desister($vente->refresh(), 'FINANCIER', null, 10);

        $this->assertSame(StatutVente::Desistee, $vente->statut);
        $this->assertSame('30000.00', $vente->montant_retenu);
        $this->assertSame('270000.00', $vente->montant_a_rembourser);
        $this->assertSame(StatutBien::Disponible, $bien->refresh()->statut);
        $this->assertSame(0, $vente->echeances()->impayes()->count());

        $depense = $this->ventes->rembourser($vente, 'VIREMENT');
        $this->assertSame('270000.00', $depense->montant);
        $this->assertSame($acquereur->id, $depense->beneficiaire_id);
    }

    public function test_un_contrat_valide_n_est_plus_modifiable(): void
    {
        [$vente] = $this->venteValidee();

        $this->expectException(DomainException::class);
        $this->ventes->definirConditions($vente, ['remise' => 100]);
    }
}
