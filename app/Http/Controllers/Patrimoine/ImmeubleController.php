<?php

namespace App\Http\Controllers\Patrimoine;

use App\Enums\RoleTiers;
use App\Http\Controllers\CrudController;
use App\Models\Immeuble;
use App\Models\Lotissement;
use App\Models\Programme;
use App\Models\Tiers;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ImmeubleController extends CrudController
{
    protected string $modele = Immeuble::class;

    protected string $route = 'immeubles';

    protected string $titre = 'Immeubles';

    protected string $titreSingulier = 'Immeuble';

    protected string $module = 'Patrimoine';

    protected array $with = ['programme', 'proprietaire'];

    protected array $recherche = ['nom', 'adresse', 'ilot', 'lot'];

    protected string $tri = 'nom';

    protected string $sens = 'asc';

    protected ?string $vueFiche = 'patrimoine.immeuble';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'nom', 'label' => 'Nom de l\'immeuble', 'requis' => true],
            ['nom' => 'programme_id', 'label' => 'Programme', 'type' => 'select', 'options' => fn () => Programme::orderBy('nom')->pluck('nom', 'id'), 'col' => 'col-md-3'],
            ['nom' => 'lotissement_id', 'label' => 'Lotissement', 'type' => 'select', 'options' => fn () => Lotissement::orderBy('nom')->pluck('nom', 'id'), 'col' => 'col-md-3'],
            ['nom' => 'proprietaire_id', 'label' => 'Propriétaire (si gestion pour compte de tiers)', 'type' => 'select', 'recherche' => true,
                'options' => fn () => Tiers::avecRole(RoleTiers::Proprietaire)->orderBy('nom')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'adresse', 'label' => 'Adresse'],
            ['nom' => 'ilot', 'label' => 'Îlot', 'col' => 'col-md-3'],
            ['nom' => 'lot', 'label' => 'Lot', 'col' => 'col-md-3'],
            ['nom' => 'nombre_etages', 'label' => 'Nombre d\'étages', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'integer', 'min:0', 'max:200']],
            ['nom' => 'annee_construction', 'label' => 'Année de construction', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'integer', 'min:1800', 'max:2100']],
            ['nom' => 'superficie', 'label' => 'Superficie (m²)', 'type' => 'number', 'col' => 'col-md-3'],
            ['nom' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'between:-90,90']],
            ['nom' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'between:-180,180']],
            ['nom' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['nom' => 'actif', 'label' => 'Actif', 'type' => 'checkbox', 'defaut' => true],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Immeuble', fn ($i) => Cellule::deuxLignes($i->nom, $i->adresse)],
            ['Programme', fn ($i) => $i->programme?->nom],
            ['Propriétaire', fn ($i) => $i->proprietaire?->nom_complet],
            ['Étages', fn ($i) => $i->nombre_etages, 'text-end'],
            ['Biens', fn ($i) => $i->biens_count, 'text-end'],
            ['Statut', fn ($i) => Cellule::actif($i->actif)],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->withCount('biens');
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $donnees['nombre_etages'] ??= 0;

        return $donnees;
    }

    protected function apresEnregistrement(Model $element, Request $request, bool $creation): void
    {
        $element->proprietaire?->ajouterRole(RoleTiers::Proprietaire);
    }

    protected function supprimable(Model $element): bool
    {
        return !$element->biens()->exists();
    }
}
