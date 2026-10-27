<?php

namespace Tests\Feature\Metier;

use App\Models\AppelFonds;
use App\Models\Copropriete;
use App\Models\Intervention;
use App\Models\Loyer;
use App\Models\Mandat;
use App\Models\Publication;
use App\Services\AgenceService;
use App\Services\Finance\EncaissementService;
use App\Services\Location\BailService;
use App\Services\Meuble\SejourService;
use App\Services\Syndic\SyndicService;
use App\Services\Vente\VenteService;
use App\Support\AgenceCourante;
use Database\Seeders\PaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

/**
 * Parcours de tous les écrans avec des données réelles, et contrôles d'accès.
 */
class NavigationTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seederRbac();
        $this->seed(PaysSeeder::class);
        $this->creerAgence('NAV');
        $this->agence->update(['vitrine_active' => true]);
        $this->donnees();
        app(AgenceCourante::class)->definir(null); // la requête HTTP résout elle-même l'agence
    }

    protected function donnees(): void
    {
        $acquereur = $this->tiers(['email' => 'acquereur@test.ci']);
        $terrain = $this->bien(['a_vendre' => true, 'prix_vente' => 5000000, 'publie' => true, 'type_bien' => 'TERRAIN']);
        $ventes = app(VenteService::class);
        $vente = $ventes->creer($acquereur);
        $ventes->ajouterBien($vente, $terrain);
        $ventes->valider($vente);

        $proprietaire = $this->tiers(['nom' => 'BAILLEUR']);
        $mandat = Mandat::create(['numero' => 'MG-T', 'proprietaire_id' => $proprietaire->id, 'date_debut' => today()->subYear(), 'taux_commission' => 10]);
        $appartement = $this->bien(['a_louer' => true, 'loyer_mensuel' => 150000, 'proprietaire_id' => $proprietaire->id, 'mandat_id' => $mandat->id]);
        $locataire = $this->tiers(['nom' => 'LOCATAIRE']);
        $baux = app(BailService::class);
        $bail = $baux->activer($baux->creer($appartement, $locataire, ['date_debut' => today()->startOfMonth()]));

        $caisse = app(EncaissementService::class);
        $recu = $caisse->encaisser(['tiers_id' => $locataire->id, 'mode_paiement' => 'ESPECES'], $caisse->repartir($caisse->dettesOuvertes($locataire), 150000 * 4 + 10000));

        $copro = Copropriete::create(['nom' => 'Résidence Test', 'total_tantiemes' => 1000]);
        $syndic = app(SyndicService::class);
        $syndic->ajouterLot($copro, ['numero' => 'L1', 'tantiemes' => 600, 'coproprietaire_id' => $acquereur->id]);
        $syndic->ajouterLot($copro, ['numero' => 'L2', 'tantiemes' => 400, 'coproprietaire_id' => $proprietaire->id]);
        $appel = AppelFonds::create(['copropriete_id' => $copro->id, 'type' => 'travaux', 'libelle' => 'Peinture', 'date_appel' => today(), 'date_echeance' => today()->addMonth(), 'montant_total' => 100000]);
        $syndic->emettre($appel);
        $ag = \App\Models\Assemblee::create(['copropriete_id' => $copro->id, 'date_heure' => now()->addWeek()]);

        $studio = $this->bien(['meuble' => true, 'prix_nuitee' => 30000]);
        $sejour = app(SejourService::class)->reserver($studio, $this->tiers(), ['date_arrivee' => today(), 'date_depart' => today()->addDays(2)]);

        $intervention = app(\App\Services\Maintenance\InterventionService::class)->creer(['objet' => 'Fuite', 'bien_id' => $appartement->id, 'a_charge_de' => 'proprietaire']);
        Publication::create(['titre' => 'Lancement', 'slug' => 'lancement', 'publie' => true, 'type' => 'actualite']);
        $enVente = $this->bien(['a_vendre' => true, 'prix_vente' => 25000000, 'publie' => true, 'libelle' => 'Villa vitrine']);
        $this->bien(['a_vendre' => true, 'prix_vente' => 1, 'publie' => false, 'libelle' => 'Bien non publié', 'reference' => 'PRIVE-1']);

        $employe = app(\App\Services\Rh\RhService::class)->embaucher(['nom' => 'KONE', 'date_embauche' => today()->subYear()], ['salaire_base' => 250000]);
        $paie = app(\App\Services\Rh\PaieService::class);
        $periode = $paie->periode(today()->startOfMonth());
        $paie->calculerTout($periode);
        $echue = \App\Models\Echeance::where('vente_id', $vente->id)->orderBy('date_echeance')->first();
        $echue?->update(['date_echeance' => today()->subDays(20)]);
        app(\App\Services\Recouvrement\RecouvrementService::class)->analyser();

        $this->ids = [
            'vente' => $vente->id, 'bien' => $terrain->id, 'tiers' => $acquereur->id, 'bail' => $bail->id, 'encaissement' => $recu->id,
            'copropriete' => $copro->id, 'appel' => $appel->id, 'ligne' => $appel->lignes()->first()->id, 'assemblee' => $ag->id, 'sejour' => $sejour->id,
            'intervention' => $intervention->id, 'quittance' => Loyer::whereNotNull('quittance_numero')->value('id'), 'vitrine_reference' => $enVente->reference,
            'terrain_reference' => $terrain->reference, 'employe' => $employe->id, 'periode' => $periode->id,
            'bulletin' => $periode->bulletins()->value('id'), 'recouvrement' => \App\Models\Recouvrement::value('id'),
            'compte' => \App\Models\Compta\Compte::where('numero', '5711')->value('id'),
        ];
    }

    public function test_tous_les_ecrans_s_affichent_pour_un_directeur(): void
    {
        $directeur = $this->utilisateur();
        $this->actingAs($directeur);

        $exclues =['logout', 'connexion', 'login', '403', 'agences.basculer', 'vitrine.accueil', 'vitrine.biens', 'vitrine.bien', 'vitrine.actualites',
            'vitrine.actualite', 'vitrine.contact', 'modifier.mot.passe', 'menuprofil', 'moncompte'];
        $erreurs = [];

        foreach (Route::getRoutes() as $route) {
            $nom = $route->getName();
            if (!$nom || !in_array('GET', $route->methods(), true) || in_array($nom, $exclues, true) || str_starts_with($nom, 'agences.')
                || str_contains($route->uri(), '{') || str_starts_with($route->uri(), '_') || $route->uri() === 'up' || str_starts_with($nom, 'storage.')) {
                continue;
            }
            // seulement les écrans autorisés au profil (les écrans du socle RBAC sont réservés au super administrateur)
            if (in_array(\App\Http\Middleware\ExigerSuperAdmin::class, $route->gatherMiddleware(), true)) {
                continue;                                                   // écrans de la plateforme
            }
            $permissions = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m) && str_starts_with($m, 'can:'))->map(fn ($m) => substr($m, 4));
            if ($permissions->contains(fn ($p) => !$directeur->can($p))) {
                continue;
            }
            $reponse = $this->get(route($nom));
            if ($reponse->baseResponse->getStatusCode() !== 200) {
                $erreurs[] = $nom . ' → ' . $reponse->baseResponse->getStatusCode() . ' ' . ($reponse->exception?->getMessage() ?? '');
            }
        }

        $this->assertSame([], $erreurs);
    }

    public function test_les_fiches_documents_et_etats_s_affichent(): void
    {
        $this->actingAs($this->utilisateur());
        $i = $this->ids;

        $urls = [
            route('ventes.show', $i['vente']), route('ventes.contrat', $i['vente']), route('biens.show', $i['bien']), route('biens.edit', $i['bien']),
            route('tiers.show', $i['tiers']), route('tiers.edit', $i['tiers']), route('baux.show', $i['bail']), route('baux.contrat', $i['bail']),
            route('encaissements.show', $i['encaissement']), route('encaissements.recu', $i['encaissement']), route('encaissements.create', ['tiers_id' => $i['tiers']]),
            route('coproprietes.show', $i['copropriete']), route('appelsfonds.show', $i['appel']), route('appelsfonds.avis', [$i['appel'], $i['ligne']]),
            route('assemblees.show', $i['assemblee']), route('sejours.show', $i['sejour']), route('sejours.facture', $i['sejour']),
            route('interventions.show', $i['intervention']), route('loyers.quittance', $i['quittance']),
            route('reversements.create', ['proprietaire_id' => \App\Models\Tiers::toutesAgences()->where('nom', 'BAILLEUR')->value('id')]),
        ];
        array_push($urls, route('employes.show', $i['employe']), route('employes.edit', $i['employe']), route('paie.show', $i['periode']),
            route('paie.bulletin', [$i['periode'], $i['bulletin']]), route('paie.livre', $i['periode']), route('recouvrements.show', $i['recouvrement']),
            route('comptabilite.grandlivre', $i['compte']), route('comptabilite.index', ['vue' => 'balance']), route('comptabilite.index', ['vue' => 'etats']),
            route('comptabilite.index', ['vue' => 'exercices']), route('comptabilite.export'), route('simulateur', ['hausse_loyers' => 5]));
        foreach (array_keys(config('aide.chapitres')) as $chapitre) {
            $urls[] = route('aide.chapitre', $chapitre);
        }
        foreach (array_keys(\App\Services\Pilotage\Indicateurs::RAPPORTS) as $rapport) {
            $urls[] = route('etats.show', $rapport);
            $urls[] = route('etats.show', [$rapport, 'format' => 'csv']);
        }

        foreach ($urls as $url) {
            $reponse = $this->get($url);
            $statut = $reponse->baseResponse->getStatusCode();
            $this->assertSame(200, $statut, $statut === 200 ? '' : $url . ' : ' . ($reponse->exception?->getMessage() ?? ''));
        }
    }

    public function test_le_site_vitrine_public(): void
    {
        $slug = $this->agence->slug;

        $this->get(route('vitrine.accueil', $slug))->assertOk()->assertSee($this->agence->raison_sociale);
        $this->get(route('vitrine.biens', $slug))->assertOk();
        $this->get(route('vitrine.biens', $slug))->assertSee('Villa vitrine')->assertDontSee('Bien non publié');
        $this->get(route('vitrine.bien', [$slug, $this->ids['vitrine_reference']]))->assertOk();
        // un bien vendu ou non publié n'est plus exposé
        $this->get(route('vitrine.bien', [$slug, $this->ids['terrain_reference']]))->assertNotFound();
        $this->get(route('vitrine.bien', [$slug, 'PRIVE-1']))->assertNotFound();
        $this->get(route('vitrine.actualites', $slug))->assertOk();
        $this->get(route('vitrine.actualite', [$slug, 'lancement']))->assertOk();
        $this->get(route('vitrine.contact', $slug))->assertOk();

        $this->post(route('vitrine.contact.envoyer', $slug), ['nom' => 'Visiteur', 'telephone' => '0700000000', 'message' => 'Bonjour'])->assertRedirect();
        $this->assertSame(1, \App\Models\DemandeContact::toutesAgences()->count());

        // robot : le champ piège rempli n'enregistre rien
        $this->post(route('vitrine.contact.envoyer', $slug), ['nom' => 'Bot', 'telephone' => '1', 'site_web' => 'spam'])->assertRedirect();
        $this->assertSame(1, \App\Models\DemandeContact::toutesAgences()->count());

        // un bien non publié n'est pas exposé
        $this->get(route('vitrine.bien', [$slug, 'INEXISTANT']))->assertNotFound();
    }

    public function test_un_commercial_ne_peut_pas_encaisser(): void
    {
        $this->actingAs($this->utilisateur('COMMERCIAL'));

        $this->get(route('ventes.index'))->assertOk();
        $this->get(route('encaissements.create'))->assertForbidden();
        $this->post(route('ventes.valider', $this->ids['vente']))->assertForbidden();
    }

    public function test_une_autre_agence_ne_voit_pas_les_donnees(): void
    {
        $autre = app(AgenceService::class)->creer(['code' => 'AUTRE', 'raison_sociale' => 'Autre agence', 'slug' => 'autre']);
        $this->actingAs($this->utilisateur("DIRECTEUR D'AGENCE", $autre));

        $this->get(route('ventes.show', $this->ids['vente']))->assertNotFound();
        $this->get(route('biens.show', $this->ids['bien']))->assertNotFound();
        $this->get(route('encaissements.recu', $this->ids['encaissement']))->assertNotFound();
        $this->get(route('tiers.show', $this->ids['tiers']))->assertNotFound();
    }

    public function test_un_module_desactive_est_inaccessible(): void
    {
        app(AgenceService::class)->synchroniserModules($this->agence, ['vente']);
        $this->actingAs($this->utilisateur());

        $this->get(route('coproprietes.index'))->assertForbidden();
        $this->get(route('ventes.index'))->assertOk();
    }

    public function test_l_espace_client_ne_montre_que_ses_documents(): void
    {
        app(AgenceCourante::class)->definir($this->agence);
        $client = $this->utilisateur('CLIENT');
        \App\Models\Tiers::find($this->ids['tiers'])->update(['user_id' => $client->id]);
        app(AgenceCourante::class)->definir(null);

        $this->actingAs($client);
        $this->get(route('dashboard'))->assertRedirect(route('moncompte'));
        $this->get(route('moncompte'))->assertOk();
        // reçu d'un autre client (le locataire)
        $this->get(route('moncompte.recu', $this->ids['encaissement']))->assertNotFound();
        $this->get(route('ventes.index'))->assertForbidden();
    }
}
