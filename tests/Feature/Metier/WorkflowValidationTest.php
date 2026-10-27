<?php

namespace Tests\Feature\Metier;

use App\Enums\StatutBail;
use App\Models\Bail;
use App\Models\Depense;
use App\Models\Role;
use App\Models\Validation\Circuit;
use App\Models\Validation\Demande;
use App\Services\Location\BailService;
use App\Support\AgenceCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

/**
 * Workflows de validation paramétrables : gouvernance des circuits, étapes, séparation des tâches, exécution après approbation.
 */
class WorkflowValidationTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seederRbac();
        $this->creerAgence('WF');
    }

    /** Circuit actif à deux étapes : gestionnaire locatif (tout montant) puis directeur (au-delà de 300 000). */
    protected function circuitBail(): Circuit
    {
        $circuit = Circuit::create(['action' => 'BAIL_ACTIVATION', 'libelle' => 'Activation des baux', 'statut' => 'ACTIF', 'active_at' => now()]);
        $circuit->etapes()->create(['ordre' => 1, 'libelle' => 'Visa du gestionnaire', 'role_id' => Role::where('name', 'GESTIONNAIRE LOCATIF')->value('id')]);
        $circuit->etapes()->create(['ordre' => 2, 'libelle' => 'Accord du directeur', 'role_id' => Role::where('name', "DIRECTEUR D'AGENCE")->value('id'), 'montant_min' => 300000]);

        return $circuit;
    }

    protected function bail(float $loyer): Bail
    {
        $baux = app(BailService::class);

        return $baux->creer($this->bien(['a_louer' => true, 'loyer_mensuel' => $loyer]), $this->tiers(), ['date_debut' => today()->startOfMonth()]);
    }

    public function test_action_soumise_validee_en_deux_etapes_puis_executee(): void
    {
        $this->circuitBail();
        $bail = $this->bail(400000);
        $demandeur = $this->utilisateur('GESTIONNAIRE LOCATIF');
        $gestionnaire = $this->utilisateur('GESTIONNAIRE LOCATIF');
        $directeur = $this->utilisateur();
        app(AgenceCourante::class)->definir(null);

        // l'activation devient une demande : le bail reste en brouillon
        $this->actingAs($demandeur)->post(route('baux.activer', $bail))->assertSessionHas('info');
        $demande = Demande::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(StatutBail::Brouillon, $bail->fresh()->statut);
        $this->assertSame(1, $demande->etape_courante);
        $this->assertEquals(400000, (float) $demande->montant);

        // séparation des tâches : le demandeur ne valide pas sa propre demande ; le directeur n'est pas à l'étape 1
        $this->actingAs($demandeur)->post(route('validations.decider', $demande), ['decision' => 'APPROUVE'])->assertSessionHas('error');
        $this->actingAs($directeur)->post(route('validations.decider', $demande), ['decision' => 'APPROUVE'])->assertSessionHas('error');

        $this->actingAs($gestionnaire)->get(route('validations.index'))->assertOk()->assertSee($demande->numero);
        $this->actingAs($gestionnaire)->post(route('validations.decider', $demande), ['decision' => 'APPROUVE'])->assertSessionHas('success');
        $this->assertSame(2, $demande->fresh()->etape_courante);   // montant > 300 000 : l'étape du directeur s'applique
        $this->assertSame(StatutBail::Brouillon, $bail->fresh()->statut);

        $this->actingAs($directeur)->get(route('validations.show', $demande))->assertOk()->assertSee('Votre décision');
        $this->actingAs($directeur)->post(route('validations.decider', $demande), ['decision' => 'APPROUVE']);
        $this->assertSame('EXECUTEE', $demande->fresh()->statut);
        $this->assertSame(StatutBail::Actif, $bail->fresh()->statut);   // exécutée automatiquement
        $this->assertSame(2, $demande->fresh()->decisions()->count());
    }

    public function test_montant_sous_le_seuil_une_seule_etape_et_rejet_motive(): void
    {
        $this->circuitBail();
        $bail = $this->bail(150000);
        $demandeur = $this->utilisateur();
        $gestionnaire = $this->utilisateur('GESTIONNAIRE LOCATIF');
        app(AgenceCourante::class)->definir(null);

        $this->actingAs($demandeur)->post(route('baux.activer', $bail));
        $demande = Demande::withoutGlobalScopes()->firstOrFail();

        // rejet sans motif refusé, puis rejet motivé : rien n'est exécuté
        $this->actingAs($gestionnaire)->post(route('validations.decider', $demande), ['decision' => 'REJETE'])->assertSessionHas('error');
        $this->actingAs($gestionnaire)->post(route('validations.decider', $demande), ['decision' => 'REJETE', 'commentaire' => 'Garant manquant']);
        $this->assertSame('REJETEE', $demande->fresh()->statut);
        $this->assertSame(StatutBail::Brouillon, $bail->fresh()->statut);

        // nouvelle soumission possible après le rejet
        $this->actingAs($demandeur)->post(route('baux.activer', $bail))->assertSessionHas('info');
        $nouvelle = Demande::withoutGlobalScopes()->latest('id')->first();
        $this->actingAs($gestionnaire)->post(route('validations.decider', $nouvelle), ['decision' => 'APPROUVE']);
        $this->assertSame('EXECUTEE', $nouvelle->fresh()->statut);       // < 300 000 : une seule étape
    }

    public function test_sans_circuit_actif_l_action_s_execute_et_gouvernance_des_circuits(): void
    {
        $auteur = $this->utilisateur();
        $autre = $this->utilisateur();
        $bail = $this->bail(100000);
        app(AgenceCourante::class)->definir(null);

        // circuit en brouillon : sans effet
        $this->actingAs($auteur)->post(route('circuits.store'), ['action' => 'BAIL_ACTIVATION', 'libelle' => 'Baux'])->assertRedirect();
        $circuit = Circuit::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($auteur)->post(route('circuits.etapes.store', $circuit), ['libelle' => 'Visa', 'role_id' => Role::where('name', 'GESTIONNAIRE LOCATIF')->value('id'), 'separation_auteur' => '1']);
        $this->assertSame(1, $circuit->etapes()->count());

        // l'auteur ne peut pas approuver son propre circuit ; un autre utilisateur oui, puis activation
        $this->actingAs($auteur)->post(route('circuits.approuver', $circuit))->assertSessionHas('error');
        $this->actingAs($autre)->post(route('circuits.approuver', $circuit))->assertSessionHas('success');
        $this->actingAs($autre)->get(route('circuits.show', $circuit))->assertOk();
        $this->actingAs($autre)->post(route('circuits.activer', $circuit))->assertSessionHas('success');
        $this->assertSame('ACTIF', $circuit->fresh()->statut);

        // actif : plus modifiable ; nouvelle version en brouillon, l'activation retire la précédente
        $this->actingAs($auteur)->post(route('circuits.etapes.store', $circuit), ['libelle' => 'X', 'role_id' => Role::first()->id])->assertSessionHas('error');
        $this->actingAs($auteur)->post(route('circuits.version', $circuit))->assertRedirect();
        $v2 = Circuit::withoutGlobalScopes()->where('version', 2)->firstOrFail();
        $this->assertSame(1, $v2->etapes()->count());
        $this->actingAs($autre)->post(route('circuits.approuver', $v2));
        $this->actingAs($autre)->post(route('circuits.activer', $v2));
        $this->assertSame('RETIRE', $circuit->fresh()->statut);

        // retrait : l'action s'exécute de nouveau directement
        $this->actingAs($autre)->post(route('circuits.retirer', $v2));
        $this->actingAs($auteur)->post(route('baux.activer', $bail))->assertSessionHas('success');
        $this->assertSame(StatutBail::Actif, $bail->fresh()->statut);
    }

    public function test_paiement_de_depense_au_dela_du_seuil_mis_en_attente_sans_comptabilisation(): void
    {
        $circuit = Circuit::create(['action' => 'DEPENSE_PAIEMENT', 'libelle' => 'Dépenses', 'statut' => 'ACTIF', 'montant_declenchement' => 500000]);
        $circuit->etapes()->create(['ordre' => 1, 'libelle' => 'Accord du directeur', 'role_id' => Role::where('name', "DIRECTEUR D'AGENCE")->value('id')]);
        $comptable = $this->utilisateur('CAISSIER / COMPTABLE');
        $directeur = $this->utilisateur();
        app(AgenceCourante::class)->definir(null);

        $donnees = ['date_depense' => today()->toDateString(), 'categorie' => 'TRAVAUX', 'libelle' => 'Réfection toiture', 'mode_paiement' => 'VIREMENT', 'statut' => 'payee'];
        $this->actingAs($comptable)->post(route('depenses.store'), $donnees + ['montant' => 200000]);    // sous le seuil : payée
        $this->actingAs($comptable)->post(route('depenses.store'), $donnees + ['montant' => 900000]);    // au-delà : à valider
        $petite = Depense::withoutGlobalScopes()->where('montant', 200000)->firstOrFail();
        $grosse = Depense::withoutGlobalScopes()->where('montant', 900000)->firstOrFail();
        $this->assertSame('payee', $petite->statut);
        $this->assertSame('a_payer', $grosse->statut);
        $this->assertSame(0, \App\Models\Compta\Ecriture::withoutGlobalScopes()->where('source_type', 'depense')->where('source_id', $grosse->id)->count());

        $demande = Demande::withoutGlobalScopes()->where('action', 'DEPENSE_PAIEMENT')->firstOrFail();
        $this->actingAs($directeur)->post(route('validations.decider', $demande), ['decision' => 'APPROUVE']);
        $this->assertSame('payee', $grosse->fresh()->statut);
        $this->assertSame(1, \App\Models\Compta\Ecriture::withoutGlobalScopes()->where('source_type', 'depense')->where('source_id', $grosse->id)->where('nature', 'normale')->count());
    }
}
