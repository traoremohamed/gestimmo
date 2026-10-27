<?php

namespace Tests\Feature\Metier;

use App\Enums\NatureLoyer;
use App\Jobs\EnvoiMessageJob;
use App\Models\Compta\Ligne;
use App\Models\DecompteSortie;
use App\Models\Depense;
use App\Models\Document;
use App\Models\Loyer;
use App\Models\Mandat;
use App\Models\QrJeton;
use App\Services\Finance\EncaissementService;
use App\Services\Location\BailService;
use App\Services\Location\SortieService;
use App\Support\AgenceCourante;
use App\Support\Pdf;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

/**
 * Sortie du locataire (garanties, compteurs CIE / SODECI, réparations), GED, signature QR et exports.
 */
class SortieGedTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function solde(string $numero): float
    {
        return (float) Ligne::whereHas('compte', fn ($q) => $q->where('numero', $numero))->selectRaw('coalesce(sum(debit) - sum(credit), 0) as s')->value('s');
    }

    protected function payerTout($tiers): void
    {
        $caisse = app(EncaissementService::class);
        $dettes = $caisse->dettesOuvertes($tiers);
        $caisse->encaisser(['tiers_id' => $tiers->id, 'mode_paiement' => 'ESPECES'], $caisse->repartir($dettes, $dettes->sum(fn ($d) => (float) $d->resteAPayer())));
    }

    /** Bail sous mandat avec dépôt 200 000, cautions SODECI 20 000 / CIE 15 000, compteur CIE à crédit ; entrée payée, avril et mai impayés. */
    protected function bailResilie(): array
    {
        Carbon::setTestNow('2026-03-10');
        $proprietaire = $this->tiers(['nom' => 'BAILLEUR']);
        $mandat = Mandat::create(['numero' => 'MG-1', 'proprietaire_id' => $proprietaire->id, 'date_debut' => '2026-01-01', 'taux_commission' => 10]);
        $bien = $this->bien(['a_louer' => true, 'loyer_mensuel' => 100000, 'proprietaire_id' => $proprietaire->id, 'mandat_id' => $mandat->id]);
        $locataire = $this->tiers(['nom' => 'SORTANT', 'email' => 'sortant@test.ci']);
        $baux = app(BailService::class);
        $bail = $baux->activer($baux->creer($bien, $locataire, ['date_debut' => '2026-03-01', 'mois_caution' => 2, 'mois_avance' => 1,
            'caution_sodeci' => 20000, 'caution_cie' => 15000, 'compteur_cie_credit' => true, 'compteur_cie_montant_credit' => 60000]));
        $this->assertSame(parametre('sortie.cautions_compteurs') ? 1 : 0, Loyer::where('bail_id', $bail->id)->where('nature', NatureLoyer::CautionSodeci->value)->count());
        $this->payerTout($locataire);

        Carbon::setTestNow('2026-05-20');
        $baux->genererLoyers($bail->refresh());
        $baux->resilier($bail, Carbon::parse('2026-05-31'), 'FIN_BAIL');
        $bail->etatsLieux()->create(['type' => 'sortie', 'date' => '2026-05-31', 'pieces' => [['piece' => 'Séjour', 'etat' => 'mauvais']], 'compteurs' => ['cie_index' => '1520'],
            'reparations' => [['libelle' => 'Peinture séjour', 'montant' => 30000, 'a_charge' => 'locataire'], ['libelle' => 'Usure robinet', 'montant' => 10000, 'a_charge' => 'proprietaire']]]);

        return [$bail->refresh(), $locataire, $proprietaire];
    }

    public function test_decompte_de_sortie_complet_garanties_compteurs_reparations_et_restitution(): void
    {
        Queue::fake();
        $this->creerAgence();
        [$bail, $locataire] = $this->bailResilie();
        $this->assertSame(-235000.0, $this->solde('165'));   // dépôt + cautions perçus

        $service = app(SortieService::class);
        $decompte = $service->preparer($bail);
        $lignes = $decompte->lignes->keyBy('type');
        $this->assertSame(200000.0, (float) $lignes['depot_garantie']->montant);
        $this->assertSame(20000.0, (float) $lignes['caution_sodeci']->montant);
        $this->assertSame(15000.0, (float) $lignes['caution_cie']->montant);
        $this->assertSame(200000.0, (float) $lignes['impaye']->montant);   // avril + mai
        $this->assertSame(30000.0, (float) $decompte->lignes->where('type', 'reparation')->sum('montant'));   // seule la réparation à la charge du locataire

        // crédit compteur CIE et dernière facture SODECI non réglée ; facture CIE réglée
        $service->modifierLigne($lignes['credit_compteur_cie'], 'Crédit compteur CIE remboursé', 25000);
        $service->modifierLigne($lignes['facture_sodeci'], 'Facture SODECI de mai', 8000);

        // justificatifs exigés avant validation
        try {
            $service->valider($decompte->refresh());
            $this->fail('Les justificatifs devraient être exigés.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Justificatif manquant', $e->getMessage());
        }
        foreach (['credit_compteur_cie', 'facture_sodeci', 'facture_cie'] as $type) {
            $service->joindre($lignes[$type], UploadedFile::fake()->create($type . '.pdf', 20, 'application/pdf'));
        }

        $decompte = $service->valider($decompte->refresh());
        // crédits 260 000 (200 000 + 20 000 + 15 000 + 25 000) − dettes 238 000 (200 000 + 30 000 + 8 000) = 22 000 à restituer
        $this->assertSame('valide', $decompte->statut);
        $this->assertSame(260000.0, (float) $decompte->total_credits);
        $this->assertSame(238000.0, (float) $decompte->total_retenues);
        $this->assertSame(22000.0, (float) $decompte->montant_a_rembourser);
        $this->assertSame(0.0, (float) $decompte->reste_du);
        $this->assertSame(0, Loyer::where('bail_id', $bail->id)->impayes()->count());   // tout est compensé
        $this->assertTrue(Depense::where('categorie', 'COMPTEUR_CIE')->where('refacturable_proprietaire', true)->exists());   // à la charge du propriétaire

        $service->restituer($decompte, ['mode_paiement' => 'ESPECES', 'date' => '2026-06-05'], UploadedFile::fake()->image('recu-signe.jpg'));
        $this->assertSame('rembourse', $decompte->refresh()->statut);

        // comptes de garanties et d'avoir soldés, écritures équilibrées
        $this->assertSame(0.0, $this->solde('165'));
        $this->assertSame(0.0, $this->solde('4191'));
        $this->assertSame((float) Ligne::sum('debit'), (float) Ligne::sum('credit'));
        Queue::assertPushed(EnvoiMessageJob::class, fn ($j) => $j->modele === 'DECOMPTE_SORTIE');
        Queue::assertPushed(EnvoiMessageJob::class, fn ($j) => $j->modele === 'RESTITUTION_GARANTIE' && str_contains($j->message, '22 000'));

        // décompte PDF signé QR et archivé dans la GED
        $this->assertNotNull(Pdf::generer('pdf.decompte_sortie', ['decompte' => $decompte->load('lignes.justificatifs', 'bail.bien', 'locataire'), 'entree' => null, 'sortie' => null],
            'decompte.pdf', objet: $decompte, type: 'DECOMPTE_SORTIE', etat: 'x')[1]);
    }

    public function test_retenues_superieures_aux_garanties_reste_du_et_options_parametrables(): void
    {
        Queue::fake();
        $this->creerAgence();
        app(\App\Services\Parametres::class)->definir('sortie.preuve_obligatoire', false);
        app(\App\Services\Parametres::class)->definir('sortie.cautions_compteurs', false);
        [$bail] = $this->bailResilie();

        $service = app(SortieService::class);
        $decompte = $service->preparer($bail);
        $this->assertFalse($decompte->lignes->contains('type', 'caution_sodeci'));   // option désactivée
        $service->ajouterLigne($decompte, 'autre_retenue', 'Clés non rendues', 50000);
        $decompte = $service->valider($decompte->refresh());

        // garanties 200 000 + crédit 0 < dettes 280 000 : 80 000 restent dus et passent en recouvrement
        $this->assertSame('reste_du', $decompte->statut);
        $this->assertSame(80000.0, (float) $decompte->reste_du);

        // écrans : bail résilié, décompte, PDF signé
        $this->seederRbac();
        $this->actingAs($this->utilisateur());
        app(AgenceCourante::class)->definir(null);
        $this->get(route('baux.show', $bail))->assertOk()->assertSee('Décompte de sortie');
        $this->get(route('decomptes.show', $decompte))->assertOk()->assertSee('Clés non rendues')->assertSee('Reste dû par le locataire');
        $this->assertStringStartsWith('%PDF', $this->get(route('decomptes.pdf', $decompte))->assertOk()->getContent());
        app(AgenceCourante::class)->definir($this->agence);
        $this->assertSame(80000.0, (float) Loyer::where('bail_id', $bail->id)->impayes()->get()->sum(fn ($l) => (float) $l->resteAPayer()));
        $this->expectException(DomainException::class);
        $service->restituer($decompte, ['mode_paiement' => 'ESPECES']);
    }

    public function test_ged_tout_format_securite_confidentialite_et_isolation(): void
    {
        $this->seederRbac();
        $agence = $this->creerAgence('GED');
        $tiers = $this->tiers();
        $directeur = $this->utilisateur();
        app(AgenceCourante::class)->definir(null);
        $this->actingAs($directeur);

        $this->post(route('ged.store'), ['categorie' => 'JUSTIFICATIF', 'documentable_type' => 'tiers', 'documentable_id' => $tiers->id, 'fichiers' => [
            UploadedFile::fake()->create('releve.pdf', 30, 'application/pdf'), UploadedFile::fake()->image('photo.jpg'),
            UploadedFile::fake()->create('tableau.xlsx', 12, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(3, Document::withoutGlobalScopes()->where('tiers_id', $tiers->id)->count());
        $this->assertSame(3, QrJeton::where('type_document', 'DOCUMENT_GED')->count());   // chaque document a sa signature

        $this->post(route('ged.store'), ['categorie' => 'AUTRE', 'fichiers' => [UploadedFile::fake()->create('pirate.php', 1, 'text/plain')]])
            ->assertSessionHas('error');
        $this->assertSame(3, Document::withoutGlobalScopes()->count());

        $doc = Document::withoutGlobalScopes()->where('extension', 'pdf')->first();
        $this->get(route('ged.show', $doc))->assertOk();
        $this->get(route('ged.telecharger', $doc))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('ged.apercu', $doc))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('ged.attestation', $doc))->assertOk();
        $this->get(route('ged.index', ['format' => 'xlsx']))->assertOk();

        // un document d'une autre agence est introuvable
        $autre = $this->creerAgence('AUTRE');
        $etranger = Document::create(['numero' => 'X', 'titre' => 'Étranger', 'chemin' => 'x', 'nom_original' => 'x.pdf', 'taille' => 1, 'empreinte_sha256' => str_repeat('0', 64)]);
        app(AgenceCourante::class)->definir(null);
        $this->get(route('ged.show', $etranger->id))->assertNotFound();
        $this->assertNotSame($agence->id, $autre->id);
    }

    public function test_signature_qr_verification_publique_et_reimpression_identique(): void
    {
        $this->seederRbac();
        $this->creerAgence('QR');
        $locataire = $this->tiers(['email' => 'qr@test.ci']);
        $baux = app(BailService::class);
        $baux->activer($baux->creer($this->bien(['a_louer' => true, 'loyer_mensuel' => 50000]), $locataire, ['date_debut' => today()->startOfMonth()]));
        Queue::fake();
        $caisse = app(EncaissementService::class);
        $dettes = $caisse->dettesOuvertes($locataire);
        $recu = $caisse->encaisser(['tiers_id' => $locataire->id, 'mode_paiement' => 'ESPECES'], $caisse->repartir($dettes, $dettes->sum(fn ($d) => (float) $d->resteAPayer())));

        // à chaque paiement : reçu signé QR archivé et joint à l'e-mail
        $jeton = QrJeton::where('type_document', 'RECU')->where('objet_id', $recu->id)->firstOrFail();
        $archive = Document::withoutGlobalScopes()->findOrFail($jeton->document_id);
        Queue::assertPushed(EnvoiMessageJob::class, fn ($j) => $j->modele === 'RECU_PAIEMENT' && $j->canal === 'EMAIL' && $j->documents === [$archive->id]);

        $this->actingAs($this->utilisateur());
        app(AgenceCourante::class)->definir(null);
        $premier = $this->get(route('encaissements.recu', $recu))->assertOk()->getContent();
        $second = $this->get(route('encaissements.recu', $recu))->assertOk()->getContent();
        $this->assertSame($premier, $second);                                  // réimpression = original archivé
        $this->assertSame(hash('sha256', $premier), $archive->empreinte_sha256);

        auth()->logout();
        $this->get(route('verifier.document', $jeton->jeton))->assertOk()->assertSee('Document authentique');
        $fichier = UploadedFile::fake()->createWithContent('recu.pdf', $premier);
        $this->post(route('verifier.comparer', $jeton->jeton), ['fichier' => $fichier])->assertOk()->assertSeeText('identique à l\'original');
        $this->post(route('verifier.comparer', $jeton->jeton), ['fichier' => UploadedFile::fake()->createWithContent('faux.pdf', $premier . 'x')])
            ->assertOk()->assertSeeText('différent de l\'original');
        $this->get(route('verifier.document', str_repeat('A', 40)))->assertOk()->assertSee('Code inconnu');
        $this->assertSame(3, (int) QrJeton::find($jeton->id)->nb_verifications);

        // annulation du reçu : nouvelle version, l'ancienne signature est révoquée
        $caisse->annuler($recu, 'Erreur');
        $this->actingAs($this->utilisateur());
        $this->get(route('encaissements.recu', $recu->refresh()))->assertOk();
        $this->assertNotNull($jeton->fresh()->revoque_at);
        auth()->logout();
        $this->get(route('verifier.document', $jeton->jeton))->assertSee('Document révoqué');
    }

    public function test_exports_excel_pdf_des_etats_comptables_et_des_listes(): void
    {
        $this->seederRbac();
        $this->creerAgence('EXP');
        $locataire = $this->tiers();
        $baux = app(BailService::class);
        $baux->activer($baux->creer($this->bien(['a_louer' => true, 'loyer_mensuel' => 80000]), $locataire, ['date_debut' => today()->startOfMonth()]));
        $this->payerTout($locataire);
        $this->actingAs($this->utilisateur());
        app(AgenceCourante::class)->definir(null);

        $urls = [
            route('comptabilite.index', ['vue' => 'balance']), route('comptabilite.index', ['vue' => 'etats']), route('comptabilite.export'),
            route('comptabilite.grandlivre', \App\Models\Compta\Compte::withoutGlobalScopes()->where('numero', '5711')->value('id')),
            route('etats.show', 'tresorerie'), route('biens.index'), route('tiers.index'), route('encaissements.index'), route('loyers.index'), route('caisse.index'),
        ];
        foreach ($urls as $url) {
            $xlsx = $this->get($url . (str_contains($url, '?') ? '&' : '?') . 'format=xlsx');
            $xlsx->assertOk();
            $this->assertStringStartsWith('PK', file_get_contents($xlsx->baseResponse->getFile()->getPathname()), $url);   // vrai classeur Excel
            $pdf = $this->get($url . (str_contains($url, '?') ? '&' : '?') . 'format=pdf');
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), $url);
        }
        $this->assertGreaterThan(0, QrJeton::where('type_document', 'EXPORT')->count());   // exports PDF signés
    }
}
