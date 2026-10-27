<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Audit;
use App\Http\Controllers\CrudController;
use App\Models\Agence;
use App\Models\Pays;
use App\Services\AgenceService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Administration de la plateforme : agences clientes, abonnement, modules activés.
 * Réservé aux utilisateurs sans agence (super administrateur).
 */
class AgenceController extends CrudController implements HasMiddleware
{
    protected string $modele = Agence::class;

    protected string $route = 'agences';

    protected string $titre = 'Agences de la plateforme';

    protected string $titreSingulier = 'Agence';

    protected string $module = 'Plateforme';

    protected array $recherche = ['raison_sociale', 'sigle', 'code', 'ville'];

    protected string $tri = 'raison_sociale';

    protected string $sens = 'asc';

    public function __construct(protected AgenceService $service)
    {
    }

    public static function middleware(): array
    {
        return [function ($request, $next) {
            // changement d'agence : contrôlé dans basculer() (agences autorisées de l'utilisateur)
            abort_unless($request->route()?->getActionMethod() === 'basculer' || $request->user()?->estPlateforme(), 403, 'Réservé à l\'administrateur de la plateforme.');

            return $next($request);
        }];
    }

    protected function champs(?Model $element = null): array
    {
        $modules = collect(config('immobilier.modules'))->map(fn ($m) => $m['libelle'] . (($m['obligatoire'] ?? false) ? ' (obligatoire)' : ''))->all();

        return [
            ['nom' => 'raison_sociale', 'label' => 'Raison sociale', 'requis' => true],
            ['nom' => 'sigle', 'label' => 'Sigle', 'col' => 'col-md-3'],
            ['nom' => 'code', 'label' => 'Code', 'col' => 'col-md-3', 'requis' => true,
                'regles' => ['required', 'alpha_dash', 'max:30', Rule::unique('agences', 'code')->ignore($element?->id)]],
            ['nom' => 'slug', 'label' => 'Adresse du site vitrine (/site/…)', 'col' => 'col-md-4', 'requis' => true,
                'regles' => ['required', 'alpha_dash', 'max:80', Rule::unique('agences', 'slug')->ignore($element?->id)]],
            ['nom' => 'domaine', 'label' => 'Domaine personnalisé', 'col' => 'col-md-4',
                'regles' => ['nullable', 'string', 'max:255', Rule::unique('agences', 'domaine')->ignore($element?->id)]],
            ['nom' => 'email', 'label' => 'E-mail', 'type' => 'email', 'col' => 'col-md-4'],
            ['nom' => 'telephone', 'label' => 'Téléphone', 'col' => 'col-md-4'],
            ['nom' => 'ville', 'label' => 'Ville', 'col' => 'col-md-4'],
            ['nom' => 'pays_id', 'label' => 'Pays', 'type' => 'select', 'options' => fn () => Pays::orderBy('libelle')->pluck('libelle', 'id'), 'col' => 'col-md-4'],
            ['nom' => 'plan', 'label' => 'Offre', 'type' => 'select', 'options' => ['essentiel' => 'Essentiel', 'pro' => 'Pro', 'entreprise' => 'Entreprise'], 'col' => 'col-md-4'],
            ['nom' => 'abonnement_fin', 'label' => 'Fin d\'abonnement', 'type' => 'date', 'col' => 'col-md-4', 'aide' => 'Vide = illimité'],
            ['nom' => 'actif', 'label' => 'Agence active', 'type' => 'checkbox', 'defaut' => true, 'col' => 'col-md-4'],
            ['nom' => 'modules', 'label' => 'Modules souscrits', 'type' => 'select', 'multiple' => true, 'options' => $modules, 'col' => 'col-12',
                'regles' => ['nullable', 'array'], 'defaut' => $element?->modulesActifs() ?? array_keys($modules)],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Agence', fn ($a) => Cellule::deuxLignes($a->raison_sociale, $a->code . ($a->ville ? ' · ' . $a->ville : ''))],
            ['Offre', fn ($a) => $a->plan],
            ['Utilisateurs', fn ($a) => $a->users_count, 'text-end'],
            ['Modules', fn ($a) => count($a->modulesActifs()), 'text-end'],
            ['Abonnement', fn ($a) => $a->abonnement_fin ? 'jusqu\'au ' . $a->abonnement_fin->format('d/m/Y') : 'Illimité'],
            ['Statut', fn ($a) => Cellule::actif($a->abonnementValide())],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->withCount('users');
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $modules = $donnees['modules'] ?? [];
        unset($donnees['modules']);

        $agence = $this->service->creer($donnees, $modules);
        $this->auditer('CREER', $agence);

        return redirect()->route('agences.index')->with('success', 'Agence créée et initialisée (listes de valeurs, frais, modules).');
    }

    public function update(Request $request, string $id)
    {
        $agence = $this->trouver($id);
        $donnees = $this->valider($request, $agence);
        $modules = $donnees['modules'] ?? [];
        unset($donnees['modules']);

        $agence->update($donnees);
        $this->service->synchroniserModules($agence, $modules);
        $this->auditer('MODIFIER', $agence);

        return redirect()->route('agences.index')->with('success', 'Agence modifiée.');
    }

    protected function supprimable(Model $element): bool
    {
        return !$element->users()->exists();
    }

    /**
     * Le super administrateur choisit l'agence dans laquelle il travaille.
     */
    public function basculer(Request $request)
    {
        $id = $request->validate(['agence_id' => ['nullable', 'exists:agences,id']])['agence_id'] ?? null;
        $user = $request->user();

        // super administrateur : toute agence (ou la plateforme) ; utilisateur : seulement ses agences autorisées
        abort_unless($user->estSuperAdmin() || ($id && in_array((int) $id, $user->agencesAutorisees(), true)), 403);
        $id ? $request->session()->put('agence_id', (int) $id) : $request->session()->forget('agence_id');
        \App\Helpers\Menu::oublierArbres();
        Audit::logSave(['action' => 'CHANGER AGENCE', 'code_piece' => $id, 'menu' => 'AGENCES', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION']);

        return redirect()->route('dashboard');
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $donnees['slug'] = Str::slug($donnees['slug']);
        $donnees['code'] = strtoupper($donnees['code']);

        return $donnees;
    }

    protected function valider(Request $request, ?Model $element = null): array
    {
        return $this->preparer(parent::valider($request, $element), $request, $element);
    }
}
