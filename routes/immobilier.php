<?php

use App\Http\Controllers\Admin\AgenceController;
use App\Http\Controllers\Admin\FraisTypeController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\MonAgenceController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Plateforme\PlateformeController;
use App\Http\Controllers\Plateforme\SauvegardeController;
use App\Http\Controllers\Admin\ParametreMetierController;
use App\Http\Controllers\Admin\ReferentielController;
use App\Http\Controllers\AideController;
use App\Http\Controllers\AlerteController;
use App\Http\Controllers\Compta\ComptabiliteController;
use App\Http\Controllers\Compta\EcritureController;
use App\Http\Controllers\Compta\PlanComptableController;
use App\Http\Controllers\EspaceClientController;
use App\Http\Controllers\Pilotage\AnalyseController;
use App\Http\Controllers\Recouvrement\RecouvrementController;
use App\Http\Controllers\Recouvrement\RegleRelanceController;
use App\Http\Controllers\Rh\AbsenceController;
use App\Http\Controllers\Rh\AvanceController;
use App\Http\Controllers\Rh\EmployeController;
use App\Http\Controllers\Rh\PaieController;
use App\Http\Controllers\Rh\PresenceController;
use App\Http\Controllers\Rh\RubriqueController;
use App\Http\Controllers\Finance\CaisseController;
use App\Http\Controllers\Finance\DepenseController;
use App\Http\Controllers\Finance\EncaissementController;
use App\Http\Controllers\Location\BailController;
use App\Http\Controllers\Location\LoyerController;
use App\Http\Controllers\Location\MandatController;
use App\Http\Controllers\Location\ReversementController;
use App\Http\Controllers\Location\SortieController;
use App\Http\Controllers\Ged\DocumentController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\Validation\CircuitController;
use App\Http\Controllers\Validation\ValidationController;
use App\Http\Controllers\Maintenance\InterventionController;
use App\Http\Controllers\Marketing\CampagneController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Meuble\SejourController;
use App\Http\Controllers\Patrimoine\BienController;
use App\Http\Controllers\Patrimoine\ImmeubleController;
use App\Http\Controllers\Patrimoine\LotissementController;
use App\Http\Controllers\Patrimoine\ProgrammeController;
use App\Http\Controllers\Pilotage\EtatController;
use App\Http\Controllers\Pilotage\ObjectifController;
use App\Http\Controllers\Syndic\AppelFondsController;
use App\Http\Controllers\Syndic\AssembleeController;
use App\Http\Controllers\Syndic\CoproprieteController;
use App\Http\Controllers\Tiers\DemandeContactController;
use App\Http\Controllers\Tiers\ProspectController;
use App\Http\Controllers\Tiers\ReclamationController;
use App\Http\Controllers\Tiers\RendezVousController;
use App\Http\Controllers\Tiers\TiersController;
use App\Http\Controllers\Vente\EcheanceController;
use App\Http\Controllers\Vente\ReservationController;
use App\Http\Controllers\Vente\VenteController;
use App\Http\Controllers\Vitrine\PublicationController;
use App\Http\Controllers\Vitrine\SiteWebController;
use App\Http\Controllers\Vitrine\SlideController;
use App\Http\Controllers\Vitrine\TemoignageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes des modules métier
|--------------------------------------------------------------------------
| Chaque groupe exige le module activé pour l'agence (module:xxx) et chaque
| action sa permission (can:xxx). Toute écriture passe par POST/PUT/DELETE.
*/

Route::middleware(['auth', 'check_first_password_changed_flag'])->group(function () {

    // ---------------------------------------------------------------- Plateforme & paramétrage
    Route::post('/agences/basculer', [AgenceController::class, 'basculer'])->name('agences.basculer');
    Route::ressource('agences', AgenceController::class);

    // canaux d'envoi : ceux de l'agence courante, ou ceux de la plateforme hors agence
    Route::get('/integrations', [IntegrationController::class, 'index'])->middleware('can:integrations-index')->name('integrations.index');
    Route::get('/integrations/apercu', [IntegrationController::class, 'apercu'])->middleware('can:integrations-index')->name('integrations.apercu');
    Route::post('/integrations/gabarit', [IntegrationController::class, 'gabarit'])->middleware('can:integrations-edit')->name('integrations.gabarit');
    Route::put('/integrations/modeles/{modele}', [IntegrationController::class, 'modele'])->middleware('can:integrations-edit')->name('integrations.modele');
    Route::post('/integrations/{code}', [IntegrationController::class, 'enregistrer'])->middleware('can:integrations-edit')->whereIn('code', ['EMAIL', 'SMS', 'WHATSAPP'])->name('integrations.enregistrer');
    Route::post('/integrations/{code}/tester', [IntegrationController::class, 'tester'])->middleware('can:integrations-edit')->whereIn('code', ['EMAIL', 'SMS', 'WHATSAPP'])->name('integrations.tester');

    // plateforme (super administrateur) : tableau de bord, sauvegardes, diagnostic de sécurité
    Route::middleware(\App\Http\Middleware\ExigerSuperAdmin::class)->group(function () {
        Route::get('/plateforme', [PlateformeController::class, 'tableau'])->middleware('can:plateforme-index')->name('plateforme.tableau');
        Route::prefix('plateforme/sauvegardes')->name('sauvegardes.')->controller(SauvegardeController::class)->group(function () {
            Route::get('/', 'index')->middleware('can:sauvegardes-index')->name('index');
            Route::get('/{sauvegarde}/telecharger', 'telecharger')->middleware('can:sauvegardes-telecharger')->name('telecharger');
            Route::post('/configuration', 'configurer')->middleware('can:sauvegardes-gerer')->name('configurer');
            Route::post('/lancer', 'lancer')->middleware(['can:sauvegardes-gerer', 'throttle:6,1'])->name('lancer');
            Route::post('/destinations', 'enregistrerDestination')->middleware('can:sauvegardes-gerer')->name('destinations.store');
            Route::post('/destinations/{destination}/test', 'testerDestination')->middleware('can:sauvegardes-gerer')->name('destinations.tester');
            Route::delete('/destinations/{destination}', 'supprimerDestination')->middleware('can:sauvegardes-gerer')->name('destinations.destroy');
        });
        Route::get('/plateforme/securite', fn () => view('systeme.securite', ['controles' => \App\Services\Securite\DiagnosticSecurite::controles()]))
            ->middleware('can:securite-index')->name('securite.index');
    });

    Route::middleware('module:patrimoine')->group(function () {
        Route::get('/monagence', [MonAgenceController::class, 'edit'])->middleware('can:monagence-edit')->name('monagence');
        Route::put('/monagence', [MonAgenceController::class, 'update'])->middleware('can:monagence-edit')->name('monagence.update');
        Route::get('/configuration/modules', [ModuleController::class, 'index'])->middleware('can:modules-index')->name('modules.index');
        Route::put('/configuration/modules', [ModuleController::class, 'update'])->middleware('can:modules-edit')->name('modules.update');

        Route::get('/parametres', [ParametreMetierController::class, 'edit'])->middleware('can:parametres-edit')->name('parametres');
        Route::put('/parametres', [ParametreMetierController::class, 'update'])->middleware('can:parametres-edit')->name('parametres.update');

        Route::get('/referentiels', [ReferentielController::class, 'index'])->middleware('can:referentiels-index')->name('referentiels');
        Route::post('/referentiels', [ReferentielController::class, 'store'])->middleware('can:referentiels-edit')->name('referentiels.store');
        Route::put('/referentiels/{referentiel}', [ReferentielController::class, 'update'])->middleware('can:referentiels-edit')->name('referentiels.update');

        Route::ressource('fraistypes', FraisTypeController::class, ['permissions' => ['create' => 'edit', 'store' => 'edit', 'destroy' => 'edit'], 'except' => ['show']]);

        Route::get('/medias/{media}', [MediaController::class, 'telecharger'])->name('medias.telecharger');
    });

    // ---------------------------------------------------------------- Patrimoine
    Route::middleware('module:patrimoine')->group(function () {
        Route::ressource('programmes', ProgrammeController::class);
        Route::ressource('lotissements', LotissementController::class);
        Route::ressource('immeubles', ImmeubleController::class);

        Route::get('/biens/generation', [BienController::class, 'generation'])->middleware('can:biens-generer')->name('biens.generation');
        Route::post('/biens/generation', [BienController::class, 'generer'])->middleware('can:biens-generer')->name('biens.generer');
        Route::post('/biens/{bien}/medias', [BienController::class, 'ajouterMedia'])->middleware('can:biens-edit')->name('biens.medias.store');
        Route::delete('/biens/{bien}/medias/{media}', [BienController::class, 'supprimerMedia'])->middleware('can:biens-edit')->name('biens.medias.destroy');
        Route::ressource('biens', BienController::class);
    });

    // ---------------------------------------------------------------- Tiers & CRM
    Route::middleware('module:tiers')->group(function () {
        Route::get('/tiers/recherche', [TiersController::class, 'recherche'])->middleware('can:tiers-index')->name('tiers.recherche');
        Route::post('/tiers/{tiers}/pieces', [TiersController::class, 'ajouterPiece'])->middleware('can:tiers-edit')->name('tiers.pieces.store');
        Route::delete('/tiers/{tiers}/pieces/{piece}', [TiersController::class, 'supprimerPiece'])->middleware('can:tiers-edit')->name('tiers.pieces.destroy');
        Route::get('/tiers/{tiers}/pieces/{piece}', [TiersController::class, 'telechargerPiece'])->middleware('can:tiers-index')->name('tiers.pieces.show');
        Route::post('/tiers/{tiers}/compte', [TiersController::class, 'creerCompte'])->middleware('can:tiers-edit')->name('tiers.compte');
        Route::ressource('tiers', TiersController::class, ['parametre' => 'tiers']);

        Route::ressource('prospects', ProspectController::class);
        Route::ressource('rendezvous', RendezVousController::class);
        Route::ressource('reclamations', ReclamationController::class, ['permissions' => ['destroy' => 'edit']]);
        Route::ressource('demandescontact', DemandeContactController::class, ['only' => ['index', 'edit', 'update']]);
    });

    // ---------------------------------------------------------------- Ventes
    Route::middleware('module:vente')->group(function () {
        Route::post('/reservations/{reservation}/liberer', [ReservationController::class, 'liberer'])->middleware('can:reservations-edit')->name('reservations.liberer');
        Route::post('/reservations/{reservation}/convertir', [ReservationController::class, 'convertir'])->middleware('can:ventes-create')->name('reservations.convertir');
        Route::ressource('reservations', ReservationController::class, ['parametre' => 'reservation', 'only' => ['index', 'create', 'store']]);

        Route::prefix('ventes/{vente}')->name('ventes.')->group(function () {
            Route::put('/conditions', [VenteController::class, 'conditions'])->middleware('can:ventes-edit')->name('conditions');
            Route::post('/biens', [VenteController::class, 'ajouterBien'])->middleware('can:ventes-edit')->name('biens.store');
            Route::delete('/biens/{bien}', [VenteController::class, 'retirerBien'])->middleware('can:ventes-edit')->name('biens.destroy');
            Route::post('/frais', [VenteController::class, 'ajouterFrais'])->middleware('can:ventes-edit')->name('frais.store');
            Route::delete('/frais/{frais}', [VenteController::class, 'retirerFrais'])->middleware('can:ventes-edit')->name('frais.destroy');
            Route::post('/frais/{frais}/document', [VenteController::class, 'deposerDocument'])->middleware('can:ventes-edit')->name('frais.document');
            Route::post('/valider', [VenteController::class, 'valider'])->middleware('can:ventes-valider')->name('valider');
            Route::post('/desister', [VenteController::class, 'desister'])->middleware('can:ventes-desister')->name('desister');
            Route::post('/rembourser', [VenteController::class, 'rembourser'])->middleware('can:ventes-rembourser')->name('rembourser');
            Route::get('/contrat', [VenteController::class, 'contrat'])->middleware('can:ventes-index')->name('contrat');
        });
        Route::ressource('ventes', VenteController::class, ['parametre' => 'vente', 'except' => ['edit', 'update']]);
        Route::get('/echeances', [EcheanceController::class, 'index'])->middleware('can:echeances-index')->name('echeances.index');
    });

    // ---------------------------------------------------------------- Gestion locative
    Route::middleware('module:location')->group(function () {
        Route::ressource('mandats', MandatController::class, ['except' => ['destroy']]);

        Route::prefix('baux/{bail}')->name('baux.')->group(function () {
            Route::post('/activer', [BailController::class, 'activer'])->middleware('can:baux-activer')->name('activer');
            Route::post('/resilier', [BailController::class, 'resilier'])->middleware('can:baux-resilier')->name('resilier');
            Route::post('/reviser', [BailController::class, 'reviser'])->middleware('can:baux-edit')->name('reviser');
            Route::post('/etats-lieux', [BailController::class, 'etatLieux'])->middleware('can:baux-edit')->name('etatslieux.store');
            Route::get('/contrat', [BailController::class, 'contrat'])->middleware('can:baux-index')->name('contrat');
            Route::post('/sortie', [SortieController::class, 'preparer'])->middleware('can:baux-sortie')->name('sortie');
        });
        Route::ressource('baux', BailController::class, ['parametre' => 'bail', 'except' => ['destroy']]);

        Route::prefix('decomptes/{decompte}')->name('decomptes.')->group(function () {
            Route::get('/', [SortieController::class, 'show'])->middleware('can:baux-index')->name('show');
            Route::get('/pdf', [SortieController::class, 'pdf'])->middleware('can:baux-index')->name('pdf');
            Route::post('/lignes', [SortieController::class, 'ajouterLigne'])->middleware('can:baux-sortie')->name('lignes.store');
            Route::post('/lignes/{ligne}', [SortieController::class, 'modifierLigne'])->middleware('can:baux-sortie')->name('lignes.update');
            Route::delete('/lignes/{ligne}', [SortieController::class, 'supprimerLigne'])->middleware('can:baux-sortie')->name('lignes.destroy');
            Route::post('/valider', [SortieController::class, 'valider'])->middleware('can:baux-sortie')->name('valider');
            Route::post('/restituer', [SortieController::class, 'restituer'])->middleware('can:baux-restituer')->name('restituer');
        });

        Route::get('/loyers', [LoyerController::class, 'index'])->middleware('can:loyers-index')->name('loyers.index');
        Route::post('/loyers/generer', [LoyerController::class, 'generer'])->middleware('can:loyers-generer')->name('loyers.generer');
        Route::post('/loyers/penalites', [LoyerController::class, 'penalites'])->middleware('can:loyers-generer')->name('loyers.penalites');
        Route::get('/loyers/{loyer}/quittance', [LoyerController::class, 'quittance'])->middleware('can:loyers-index')->name('loyers.quittance');

        Route::post('/reversements/{reversement}/valider', [ReversementController::class, 'valider'])->middleware('can:reversements-edit')->name('reversements.valider');
        Route::post('/reversements/{reversement}/payer', [ReversementController::class, 'payer'])->middleware('can:reversements-edit')->name('reversements.payer');
        Route::get('/reversements/{reversement}/releve', [ReversementController::class, 'releve'])->middleware('can:reversements-index')->name('reversements.releve');
        Route::ressource('reversements', ReversementController::class, ['parametre' => 'reversement', 'only' => ['index', 'create', 'store', 'show', 'destroy'], 'permissions' => ['destroy' => 'edit']]);
    });

    // ---------------------------------------------------------------- Syndic de copropriété
    Route::middleware('module:syndic')->group(function () {
        Route::prefix('coproprietes/{copropriete}')->name('coproprietes.')->group(function () {
            Route::post('/lots', [CoproprieteController::class, 'ajouterLot'])->middleware('can:coproprietes-edit')->name('lots.store');
            Route::put('/lots/{lot}', [CoproprieteController::class, 'modifierLot'])->middleware('can:coproprietes-edit')->name('lots.update');
            Route::delete('/lots/{lot}', [CoproprieteController::class, 'supprimerLot'])->middleware('can:coproprietes-edit')->name('lots.destroy');
            Route::post('/cles', [CoproprieteController::class, 'ajouterCle'])->middleware('can:coproprietes-edit')->name('cles.store');
            Route::put('/cles/{cle}', [CoproprieteController::class, 'tantiemesCle'])->middleware('can:coproprietes-edit')->name('cles.update');
            Route::post('/budgets', [CoproprieteController::class, 'enregistrerBudget'])->middleware('can:coproprietes-edit')->name('budgets.store');
            Route::post('/budgets/{budget}/voter', [CoproprieteController::class, 'voterBudget'])->middleware('can:coproprietes-edit')->name('budgets.voter');
            Route::post('/budgets/{budget}/appels', [CoproprieteController::class, 'genererAppels'])->middleware('can:appelsfonds-create')->name('budgets.appels');
        });
        Route::ressource('coproprietes', CoproprieteController::class, ['parametre' => 'copropriete', 'except' => ['destroy']]);

        Route::post('/appelsfonds/{appel}/emettre', [AppelFondsController::class, 'emettre'])->middleware('can:appelsfonds-emettre')->name('appelsfonds.emettre');
        Route::post('/appelsfonds/{appel}/annuler', [AppelFondsController::class, 'annuler'])->middleware('can:appelsfonds-emettre')->name('appelsfonds.annuler');
        Route::get('/appelsfonds/{appel}/avis/{ligne}', [AppelFondsController::class, 'avis'])->middleware('can:appelsfonds-index')->name('appelsfonds.avis');
        Route::ressource('appelsfonds', AppelFondsController::class, ['parametre' => 'appel', 'only' => ['index', 'create', 'store', 'show']]);

        Route::post('/assemblees/{assemblee}/convoquer', [AssembleeController::class, 'convoquer'])->middleware('can:assemblees-edit')->name('assemblees.convoquer');
        Route::post('/assemblees/{assemblee}/resolutions', [AssembleeController::class, 'ajouterResolution'])->middleware('can:assemblees-edit')->name('assemblees.resolutions.store');
        Route::put('/assemblees/{assemblee}/resolutions/{resolution}', [AssembleeController::class, 'voter'])->middleware('can:assemblees-edit')->name('assemblees.resolutions.voter');
        Route::post('/assemblees/{assemblee}/cloturer', [AssembleeController::class, 'cloturer'])->middleware('can:assemblees-edit')->name('assemblees.cloturer');
        Route::ressource('assemblees', AssembleeController::class, ['except' => ['destroy']]);
    });

    // ---------------------------------------------------------------- Interventions
    Route::middleware('module:maintenance')->group(function () {
        Route::post('/interventions/{intervention}/terminer', [InterventionController::class, 'terminer'])->middleware('can:interventions-edit')->name('interventions.terminer');
        Route::ressource('interventions', InterventionController::class, ['except' => ['destroy']]);
    });

    // ---------------------------------------------------------------- Résidences meublées
    Route::middleware('module:meuble')->group(function () {
        Route::post('/sejours/{sejour}/statut', [SejourController::class, 'statut'])->middleware('can:sejours-edit')->name('sejours.statut');
        Route::get('/sejours/{sejour}/facture', [SejourController::class, 'facture'])->middleware('can:sejours-index')->name('sejours.facture');
        Route::ressource('sejours', SejourController::class, ['parametre' => 'sejour', 'only' => ['index', 'create', 'store', 'show']]);
    });

    // ---------------------------------------------------------------- Caisse & finances
    Route::middleware('module:finance')->group(function () {
        Route::get('/encaissements/{encaissement}/recu', [EncaissementController::class, 'recu'])->middleware('can:encaissements-index')->name('encaissements.recu');
        Route::post('/encaissements/{encaissement}/annuler', [EncaissementController::class, 'annuler'])->middleware('can:encaissements-annuler')->name('encaissements.annuler');
        Route::ressource('encaissements', EncaissementController::class, ['parametre' => 'encaissement', 'only' => ['index', 'create', 'store', 'show']]);
        Route::ressource('depenses', DepenseController::class, ['except' => ['destroy']]);
        Route::get('/caisse', [CaisseController::class, 'index'])->middleware('can:caisse-index')->name('caisse.index');
    });

    // ---------------------------------------------------------------- Marketing
    Route::middleware('module:marketing')->group(function () {
        Route::post('/campagnes/{campagne}/envoyer', [CampagneController::class, 'envoyer'])->middleware('can:campagnes-envoyer')->name('campagnes.envoyer');
        Route::ressource('campagnes', CampagneController::class, ['permissions' => ['edit' => 'create', 'update' => 'create', 'destroy' => 'create']]);
    });

    // ---------------------------------------------------------------- Site vitrine (administration)
    Route::middleware('module:vitrine')->group(function () {
        // site paramétrable : réglages, pages, blocs, aperçu, publication
        Route::get('/siteweb', [SiteWebController::class, 'index'])->middleware('can:siteweb-index')->name('siteweb.index');
        Route::put('/siteweb/reglages', [SiteWebController::class, 'reglages'])->middleware('can:siteweb-edit')->name('siteweb.reglages');
        Route::post('/siteweb/modele', [SiteWebController::class, 'modele'])->middleware('can:siteweb-edit')->name('siteweb.modele');
        Route::post('/siteweb/publier', [SiteWebController::class, 'publier'])->middleware('can:siteweb-publier')->name('siteweb.publier');
        Route::get('/siteweb/apercu/{page?}', [SiteWebController::class, 'apercu'])->middleware('can:siteweb-index')->name('siteweb.apercu');
        Route::post('/siteweb/pages', [SiteWebController::class, 'creerPage'])->middleware('can:siteweb-edit')->name('siteweb.pages.store');
        Route::get('/siteweb/pages/{page}', [SiteWebController::class, 'page'])->middleware('can:siteweb-index')->name('siteweb.pages.edit');
        Route::put('/siteweb/pages/{page}', [SiteWebController::class, 'modifierPage'])->middleware('can:siteweb-edit')->name('siteweb.pages.update');
        Route::delete('/siteweb/pages/{page}', [SiteWebController::class, 'supprimerPage'])->middleware('can:siteweb-edit')->name('siteweb.pages.destroy');
        Route::post('/siteweb/pages/{page}/blocs', [SiteWebController::class, 'ajouterSection'])->middleware('can:siteweb-edit')->name('siteweb.sections.store');
        Route::get('/siteweb/blocs/{section}', [SiteWebController::class, 'section'])->middleware('can:siteweb-index')->name('siteweb.sections.edit');
        Route::put('/siteweb/blocs/{section}', [SiteWebController::class, 'modifierSection'])->middleware('can:siteweb-edit')->name('siteweb.sections.update');
        Route::post('/siteweb/blocs/{section}/deplacer/{sens}', [SiteWebController::class, 'deplacerSection'])->middleware('can:siteweb-edit')->whereIn('sens', ['haut', 'bas'])->name('siteweb.sections.deplacer');
        Route::post('/siteweb/blocs/{section}/visibilite', [SiteWebController::class, 'basculerSection'])->middleware('can:siteweb-edit')->name('siteweb.sections.visibilite');
        Route::delete('/siteweb/blocs/{section}', [SiteWebController::class, 'supprimerSection'])->middleware('can:siteweb-edit')->name('siteweb.sections.destroy');

        Route::ressource('publications', PublicationController::class, ['except' => ['show'], 'permissions' => ['destroy' => 'edit']]);
        Route::ressource('slides', SlideController::class, ['except' => ['show'], 'permissions' => ['destroy' => 'edit']]);
        Route::ressource('temoignages', TemoignageController::class, ['except' => ['show'], 'permissions' => ['destroy' => 'edit']]);
    });

    // ---------------------------------------------------------------- Pilotage
    Route::middleware('module:pilotage')->group(function () {
        Route::get('/etats', [EtatController::class, 'index'])->middleware('can:etats-index')->name('etats.index');
        Route::get('/etats/{rapport}', [EtatController::class, 'show'])->middleware('can:etats-index')->name('etats.show');
        Route::ressource('objectifs', ObjectifController::class, ['except' => ['show'], 'permissions' => ['destroy' => 'edit']]);
    });

    // ---------------------------------------------------------------- Recouvrement & relances
    Route::middleware('module:recouvrement')->group(function () {
        Route::get('/recouvrements', [RecouvrementController::class, 'index'])->middleware('can:recouvrements-index')->name('recouvrements.index');
        Route::post('/recouvrements/analyser', [RecouvrementController::class, 'analyser'])->middleware('can:recouvrements-edit')->name('recouvrements.analyser');
        Route::post('/recouvrements/relancer', [RecouvrementController::class, 'relancerTout'])->middleware('can:recouvrements-relancer')->name('recouvrements.relancerTout');
        Route::get('/recouvrements/{recouvrement}', [RecouvrementController::class, 'show'])->middleware('can:recouvrements-index')->name('recouvrements.show');
        Route::post('/recouvrements/{recouvrement}/relancer', [RecouvrementController::class, 'relancer'])->middleware('can:recouvrements-relancer')->name('recouvrements.relancer');
        Route::post('/recouvrements/{recouvrement}/promesse', [RecouvrementController::class, 'promettre'])->middleware('can:recouvrements-edit')->name('recouvrements.promettre');
        Route::post('/recouvrements/{recouvrement}/traiter', [RecouvrementController::class, 'traiter'])->middleware('can:recouvrements-edit')->name('recouvrements.traiter');
        Route::get('/recouvrements/{recouvrement}/lettre/{relance}', [RecouvrementController::class, 'lettre'])->middleware('can:recouvrements-index')->name('recouvrements.lettre');
        Route::ressource('reglesrelance', RegleRelanceController::class, ['except' => ['show'], 'permissions' => ['create' => 'edit', 'store' => 'edit', 'destroy' => 'edit']]);
    });

    // ---------------------------------------------------------------- RH & personnel
    Route::middleware('module:rh')->group(function () {
        Route::post('/employes/{employe}/contrat', [EmployeController::class, 'nouveauContrat'])->middleware('can:employes-edit')->name('employes.contrat');
        Route::post('/employes/{employe}/evenement', [EmployeController::class, 'evenement'])->middleware('can:employes-edit')->name('employes.evenement');
        Route::post('/employes/{employe}/sortie', [EmployeController::class, 'sortie'])->middleware('can:employes-edit')->name('employes.sortie');
        Route::ressource('employes', EmployeController::class, ['except' => ['destroy']]);

        Route::get('/absences', [AbsenceController::class, 'index'])->middleware('can:absences-index')->name('absences.index');
        Route::post('/absences', [AbsenceController::class, 'store'])->middleware('can:absences-create')->name('absences.store');
        Route::post('/absences/{absence}/decider', [AbsenceController::class, 'decider'])->middleware('can:absences-valider')->name('absences.decider');
        Route::post('/absences/{absence}/annuler', [AbsenceController::class, 'annuler'])->middleware('can:absences-create')->name('absences.annuler');

        Route::get('/presences', [PresenceController::class, 'index'])->middleware('can:presences-index')->name('presences.index');
        Route::post('/presences', [PresenceController::class, 'store'])->middleware('can:presences-edit')->name('presences.store');

        Route::get('/avances', [AvanceController::class, 'index'])->middleware('can:avances-index')->name('avances.index');
        Route::post('/avances', [AvanceController::class, 'store'])->middleware('can:avances-create')->name('avances.store');
    });

    // ---------------------------------------------------------------- Paie
    Route::middleware('module:paie')->group(function () {
        Route::get('/paie', [PaieController::class, 'index'])->middleware('can:paie-index')->name('paie.index');
        Route::post('/paie', [PaieController::class, 'ouvrir'])->middleware('can:paie-calculer')->name('paie.ouvrir');
        Route::prefix('paie/{periode}')->name('paie.')->group(function () {
            Route::get('/', [PaieController::class, 'show'])->middleware('can:paie-index')->name('show');
            Route::post('/variables', [PaieController::class, 'variable'])->middleware('can:paie-calculer')->name('variables.store');
            Route::delete('/variables/{variable}', [PaieController::class, 'supprimerVariable'])->middleware('can:paie-calculer')->name('variables.destroy');
            Route::post('/calculer', [PaieController::class, 'calculer'])->middleware('can:paie-calculer')->name('calculer');
            Route::post('/valider', [PaieController::class, 'valider'])->middleware('can:paie-valider')->name('valider');
            Route::post('/payer', [PaieController::class, 'payer'])->middleware('can:paie-payer')->name('payer');
            Route::get('/bulletins/{bulletin}', [PaieController::class, 'bulletin'])->middleware('can:paie-index')->name('bulletin');
            Route::get('/livre', [PaieController::class, 'livre'])->middleware('can:paie-index')->name('livre');
        });
        Route::ressource('rubriques', RubriqueController::class, ['except' => ['show'], 'permissions' => ['create' => 'edit', 'store' => 'edit', 'destroy' => 'edit']]);
    });

    // ---------------------------------------------------------------- Comptabilité
    Route::middleware('module:comptabilite')->group(function () {
        Route::get('/ecritures', [EcritureController::class, 'index'])->middleware('can:ecritures-index')->name('ecritures.index');
        Route::post('/ecritures', [EcritureController::class, 'store'])->middleware('can:ecritures-create')->name('ecritures.store');
        Route::post('/ecritures/generer', [EcritureController::class, 'generer'])->middleware('can:ecritures-generer')->name('ecritures.generer');
        Route::post('/ecritures/{ecriture}/contrepasser', [EcritureController::class, 'contrepasser'])->middleware('can:ecritures-create')->name('ecritures.contrepasser');

        Route::get('/comptabilite', [ComptabiliteController::class, 'index'])->middleware('can:comptabilite-index')->name('comptabilite.index');
        Route::get('/comptabilite/export', [ComptabiliteController::class, 'export'])->middleware('can:comptabilite-index')->name('comptabilite.export');
        Route::get('/comptabilite/comptes/{compte}', [ComptabiliteController::class, 'grandLivre'])->middleware('can:comptabilite-index')->name('comptabilite.grandlivre');
        Route::post('/comptabilite/exercices/{exercice}/cloturer', [ComptabiliteController::class, 'cloturer'])->middleware('can:comptabilite-cloturer')->name('comptabilite.cloturer');

        Route::get('/plancomptable', [PlanComptableController::class, 'index'])->middleware('can:plancomptable-index')->name('plancomptable.index');
        Route::put('/plancomptable/schemas', [PlanComptableController::class, 'updateSchemas'])->middleware('can:plancomptable-edit')->name('plancomptable.schemas');
        Route::post('/plancomptable/comptes', [PlanComptableController::class, 'storeCompte'])->middleware('can:plancomptable-edit')->name('plancomptable.comptes.store');
        Route::put('/plancomptable/comptes/{compte}', [PlanComptableController::class, 'updateCompte'])->middleware('can:plancomptable-edit')->name('plancomptable.comptes.update');
    });

    // ---------------------------------------------------------------- Analyse & décision
    Route::middleware('module:analyse')->group(function () {
        Route::get('/analyse', [AnalyseController::class, 'index'])->middleware('can:analyse-index')->name('analyse');
        Route::get('/simulateur', [AnalyseController::class, 'simulateur'])->middleware('can:simulateur-index')->name('simulateur');
    });

    // ---------------------------------------------------------------- Workflows de validation
    Route::middleware('module:validation')->group(function () {
        Route::get('/validations', [ValidationController::class, 'index'])->middleware('can:validations-index')->name('validations.index');
        Route::get('/validations/{demande}', [ValidationController::class, 'show'])->middleware('can:validations-index')->name('validations.show');
        Route::post('/validations/{demande}/decider', [ValidationController::class, 'decider'])->middleware('can:validations-index')->name('validations.decider');
        Route::post('/validations/{demande}/annuler', [ValidationController::class, 'annuler'])->middleware('can:validations-index')->name('validations.annuler');
        Route::post('/validations/{demande}/executer', [ValidationController::class, 'executer'])->middleware('can:validations-toutes')->name('validations.executer');

        Route::get('/circuits', [CircuitController::class, 'index'])->middleware('can:circuits-index')->name('circuits.index');
        Route::post('/circuits', [CircuitController::class, 'store'])->middleware('can:circuits-edit')->name('circuits.store');
        Route::get('/circuits/{circuit}', [CircuitController::class, 'show'])->middleware('can:circuits-index')->name('circuits.show');
        Route::put('/circuits/{circuit}', [CircuitController::class, 'update'])->middleware('can:circuits-edit')->name('circuits.update');
        Route::post('/circuits/{circuit}/etapes', [CircuitController::class, 'ajouterEtape'])->middleware('can:circuits-edit')->name('circuits.etapes.store');
        Route::put('/circuits/{circuit}/etapes/{etape}', [CircuitController::class, 'modifierEtape'])->middleware('can:circuits-edit')->name('circuits.etapes.update');
        Route::delete('/circuits/{circuit}/etapes/{etape}', [CircuitController::class, 'supprimerEtape'])->middleware('can:circuits-edit')->name('circuits.etapes.destroy');
        Route::post('/circuits/{circuit}/approuver', [CircuitController::class, 'approuver'])->middleware('can:circuits-approuver')->name('circuits.approuver');
        Route::post('/circuits/{circuit}/activer', [CircuitController::class, 'activer'])->middleware('can:circuits-approuver')->name('circuits.activer');
        Route::post('/circuits/{circuit}/retirer', [CircuitController::class, 'retirer'])->middleware('can:circuits-approuver')->name('circuits.retirer');
        Route::post('/circuits/{circuit}/version', [CircuitController::class, 'nouvelleVersion'])->middleware('can:circuits-edit')->name('circuits.version');
    });

    // ---------------------------------------------------------------- GED
    Route::middleware('module:ged')->group(function () {
        Route::get('/ged', [DocumentController::class, 'index'])->middleware('can:ged-index')->name('ged.index');
        Route::post('/ged', [DocumentController::class, 'store'])->middleware('can:ged-create')->name('ged.store');
        Route::get('/ged/{document}', [DocumentController::class, 'show'])->middleware('can:ged-index')->name('ged.show');
        Route::get('/ged/{document}/telecharger', [DocumentController::class, 'telecharger'])->middleware('can:ged-index')->name('ged.telecharger');
        Route::get('/ged/{document}/apercu', [DocumentController::class, 'apercu'])->middleware('can:ged-index')->name('ged.apercu');
        Route::get('/ged/{document}/attestation', [DocumentController::class, 'attestation'])->middleware('can:ged-index')->name('ged.attestation');
        Route::post('/ged/{document}/version', [DocumentController::class, 'version'])->middleware('can:ged-create')->name('ged.version');
        Route::delete('/ged/{document}', [DocumentController::class, 'destroy'])->middleware('can:ged-delete')->name('ged.destroy');
    });

    // ---------------------------------------------------------------- Alertes & aide (tous les utilisateurs)
    Route::get('/alertes', [AlerteController::class, 'index'])->name('alertes');
    Route::get('/aide', [AideController::class, 'index'])->name('aide.index');
    Route::get('/aide/{chapitre}', [AideController::class, 'chapitre'])->name('aide.chapitre');

    // ---------------------------------------------------------------- Espace client
    Route::middleware(['module:espace', 'can:moncompte-index'])->group(function () {
        Route::get('/moncompte', [EspaceClientController::class, 'index'])->name('moncompte');
        Route::get('/moncompte/recus/{encaissement}', [EspaceClientController::class, 'recu'])->name('moncompte.recu');
        Route::get('/moncompte/quittances/{loyer}', [EspaceClientController::class, 'quittance'])->name('moncompte.quittance');
    });
});

// ---------------------------------------------------------------- Vérification publique des documents signés QR
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/verifier/{jeton}', [VerificationController::class, 'show'])->where('jeton', '[A-Za-z0-9]{20,64}')->name('verifier.document');
    Route::post('/verifier/{jeton}', [VerificationController::class, 'comparer'])->where('jeton', '[A-Za-z0-9]{20,64}')->name('verifier.comparer');
});
