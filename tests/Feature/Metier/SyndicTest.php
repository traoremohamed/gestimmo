<?php

namespace Tests\Feature\Metier;

use App\Enums\StatutAppelFonds;
use App\Models\AppelFonds;
use App\Models\Assemblee;
use App\Models\Copropriete;
use App\Services\Finance\EncaissementService;
use App\Services\Syndic\SyndicService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

class SyndicTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected SyndicService $syndic;

    protected Copropriete $copro;

    protected array $coproprietaires = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->creerAgence();
        $this->syndic = app(SyndicService::class);

        $this->copro = Copropriete::create(['nom' => 'Résidence Les Palmiers', 'total_tantiemes' => 1000, 'taux_fonds_travaux' => 5]);
        foreach (['A1' => 500, 'A2' => 300, 'A3' => 200] as $numero => $tantiemes) {
            $tiers = $this->tiers(['nom' => 'COPRO ' . $numero]);
            $this->coproprietaires[$numero] = $tiers;
            $this->syndic->ajouterLot($this->copro, ['numero' => $numero, 'tantiemes' => $tantiemes, 'coproprietaire_id' => $tiers->id]);
        }
    }

    public function test_budget_vote_puis_appels_trimestriels_repartis_aux_tantiemes(): void
    {
        $this->assertTrue($this->copro->tantiemesEquilibres());
        $this->assertTrue($this->coproprietaires['A1']->aRole('coproprietaire'));

        $budget = $this->syndic->enregistrerBudget($this->copro, 2026, [
            ['poste' => 'GARDIENNAGE', 'montant' => 800000],
            ['poste' => 'ENTRETIEN', 'montant' => 400000],
        ]);
        $this->assertSame('1200000.00', $budget->montant_total);

        $this->syndic->voterBudget($budget);
        $appels = $this->syndic->genererAppelsProvision($budget->refresh(), 4);

        // 4 provisions + 4 cotisations au fonds de travaux (5 %)
        $this->assertCount(8, $appels);
        $this->assertSame('300000.00', $appels[0]->montant_total);
        $this->assertSame('15000.00', AppelFonds::where('type', 'fonds_travaux')->value('montant_total'));

        $appel = $this->syndic->emettre($appels[0]);
        $this->assertSame(StatutAppelFonds::Emis, $appel->statut);
        $this->assertSame(['150000.00', '90000.00', '60000.00'], $appel->lignes()->orderByDesc('tantiemes')->pluck('montant')->all());
    }

    public function test_paiement_de_toutes_les_quotes_parts_solde_l_appel(): void
    {
        $appel = AppelFonds::create(['copropriete_id' => $this->copro->id, 'type' => 'travaux', 'libelle' => 'Réfection toiture',
            'date_appel' => today(), 'date_echeance' => today()->addMonth(), 'montant_total' => 100000, 'statut' => 'brouillon']);
        $this->syndic->emettre($appel);

        $caisse = app(EncaissementService::class);
        foreach ($this->coproprietaires as $tiers) {
            $caisse->encaisser(['tiers_id' => $tiers->id, 'mode_paiement' => 'CHEQUE'],
                $caisse->repartir($caisse->dettesOuvertes($tiers, 'syndic'), $caisse->dettesOuvertes($tiers, 'syndic')->sum(fn ($d) => $d->resteAPayer())));
        }

        $this->assertSame(StatutAppelFonds::Solde, $appel->refresh()->statut);
        $this->assertSame('0.00', $this->syndic->situation($this->copro, $this->coproprietaires['A1'])['reste']);
    }

    public function test_les_majorites_de_vote(): void
    {
        $ag = Assemblee::create(['copropriete_id' => $this->copro->id, 'date_heure' => now(), 'type' => 'ordinaire']);

        $simple = $ag->resolutions()->create(['numero' => 1, 'intitule' => 'Approbation des comptes', 'majorite' => 'simple']);
        $this->assertTrue($this->syndic->voter($simple, 300, 200, 500)->adoptee);

        $absolue = $ag->resolutions()->create(['numero' => 2, 'intitule' => 'Désignation du syndic', 'majorite' => 'absolue']);
        $this->assertFalse($this->syndic->voter($absolue, 500, 200)->adoptee); // il faut plus de 500 / 1000

        $double = $ag->resolutions()->create(['numero' => 3, 'intitule' => 'Travaux d\'amélioration', 'majorite' => 'double']);
        $this->assertTrue($this->syndic->voter($double, 700, 300)->adoptee);

        $this->expectException(DomainException::class);
        $this->syndic->voter($simple, 900, 200);
    }
}
