<?php

namespace App\Providers;

use App\Services\Numerotation;
use App\Services\Parametres;
use App\Support\AgenceCourante;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Branche la plateforme immobilière : agence courante, services métier,
 * middlewares (agence, module) et routes des modules.
 */
class ImmobilierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgenceCourante::class);
        $this->app->singleton(Parametres::class);
        $this->app->singleton(Numerotation::class);
    }

    public function boot(Router $router): void
    {
        // noms courts stockés dans les colonnes polymorphes (affectations, médias)
        Relation::morphMap([
            'echeance' => \App\Models\Echeance::class,
            'vente_frais' => \App\Models\VenteFrais::class,
            'loyer' => \App\Models\Loyer::class,
            'appel_ligne' => \App\Models\AppelLigne::class,
            'sejour' => \App\Models\Sejour::class,
            'bien' => \App\Models\Bien::class,
            'programme' => \App\Models\Programme::class,
            'lotissement' => \App\Models\Lotissement::class,
            'immeuble' => \App\Models\Immeuble::class,
            'copropriete' => \App\Models\Copropriete::class,
            'vente' => \App\Models\Vente::class,
            'bail' => \App\Models\Bail::class,
            'assemblee' => \App\Models\Assemblee::class,
            'intervention' => \App\Models\Intervention::class,
            'encaissement' => \App\Models\Encaissement::class,
            'depense' => \App\Models\Depense::class,
            'reversement' => \App\Models\Reversement::class,
            'tiers' => \App\Models\Tiers::class,
            'bulletin' => \App\Models\Rh\Bulletin::class,
            'periode_paie' => \App\Models\Rh\PeriodePaie::class,
            'paiement_salaire' => \App\Models\Rh\PaiementSalaire::class,
            'avance_salaire' => \App\Models\Rh\Avance::class,
            'employe' => \App\Models\Rh\Employe::class,
            'relance' => \App\Models\Relance::class,
            'recouvrement' => \App\Models\Recouvrement::class,
            'appel_fonds' => \App\Models\AppelFonds::class,
            'mandat' => \App\Models\Mandat::class,
            'etat_lieux' => \App\Models\EtatLieux::class,
            'decompte_sortie' => \App\Models\DecompteSortie::class,
            'decompte_ligne' => \App\Models\DecompteLigne::class,
            'document' => \App\Models\Document::class,
            'demande_validation' => \App\Models\Validation\Demande::class,
            'site_page' => \App\Models\Site\Page::class,
        ]);

        // middlewares « agence courante » et « module » : déclarés dans bootstrap/app.php

        Paginator::useBootstrapFive();

        /*
         * Route::ressource('biens', BienController::class, ['parametre' => 'bien'])
         * = Route::resource + une permission par action : <nom>-index / -create / -edit / -delete.
         */
        Route::macro('ressource', function (string $nom, string $controleur, array $options = []) {
            $prefixe = $options['permission'] ?? $nom;
            // même ordre que Route::resource : /create doit être déclaré avant /{id}
            $correspondance = array_merge([
                'index' => 'index', 'create' => 'create', 'store' => 'create', 'show' => 'index',
                'edit' => 'edit', 'update' => 'edit', 'destroy' => 'delete',
            ], $options['permissions'] ?? []);
            $actions = array_intersect(array_keys($correspondance), array_diff($options['only'] ?? array_keys($correspondance), $options['except'] ?? []));

            foreach ($actions as $action) {
                Route::resource($nom, $controleur)->only([$action])
                    ->parameters([$nom => $options['parametre'] ?? 'id'])
                    ->middleware("can:{$prefixe}-{$correspondance[$action]}");
            }
        });

        Route::middleware('web')->group(base_path('routes/immobilier.php'));
        Route::middleware('web')->group(base_path('routes/vitrine.php'));
    }
}
