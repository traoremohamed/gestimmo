<?php

namespace App\Http\Controllers\Patrimoine;

use App\Http\Controllers\CrudController;
use App\Models\Programme;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;

class ProgrammeController extends CrudController
{
    protected string $modele = Programme::class;

    protected string $route = 'programmes';

    protected string $titre = 'Programmes / sites';

    protected string $titreSingulier = 'Programme';

    protected string $module = 'Patrimoine';

    protected array $recherche = ['nom', 'code', 'ville', 'commune'];

    protected string $tri = 'nom';

    protected string $sens = 'asc';

    protected ?string $vueFiche = 'patrimoine.programme';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'nom', 'label' => 'Nom du programme', 'requis' => true],
            ['nom' => 'code', 'label' => 'Code', 'col' => 'col-md-3'],
            ['nom' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => Programme::TYPES, 'requis' => true, 'col' => 'col-md-3'],
            ['nom' => 'ville', 'label' => 'Ville', 'col' => 'col-md-4'],
            ['nom' => 'commune', 'label' => 'Commune / quartier', 'col' => 'col-md-4'],
            ['nom' => 'adresse', 'label' => 'Adresse', 'col' => 'col-md-4'],
            ['nom' => 'date_lancement', 'label' => 'Date de lancement', 'type' => 'date', 'col' => 'col-md-3'],
            ['nom' => 'date_livraison_prevue', 'label' => 'Livraison prévue', 'type' => 'date', 'col' => 'col-md-3'],
            ['nom' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'between:-90,90']],
            ['nom' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'between:-180,180']],
            ['nom' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['nom' => 'publie', 'label' => 'Publié sur le site vitrine', 'type' => 'checkbox'],
            ['nom' => 'actif', 'label' => 'Actif', 'type' => 'checkbox', 'defaut' => true],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Programme', fn ($p) => Cellule::deuxLignes($p->nom, $p->code)],
            ['Type', fn ($p) => Programme::TYPES[$p->type] ?? $p->type],
            ['Localisation', fn ($p) => collect([$p->commune, $p->ville])->filter()->implode(', ')],
            ['Biens', fn ($p) => $p->biens_count, 'text-end'],
            ['Publié', fn ($p) => Cellule::oui($p->publie), 'text-center'],
            ['Statut', fn ($p) => Cellule::actif($p->actif)],
        ];
    }

    protected function filtrer($query, $request): \Illuminate\Database\Eloquent\Builder
    {
        return $query->withCount('biens')->when($request->filled('type'), fn ($q) => $q->where('type', $request->type));
    }

    protected function filtres(): array
    {
        return [['nom' => 'type', 'label' => 'Type', 'options' => Programme::TYPES]];
    }

    protected function supprimable(Model $element): bool
    {
        return !$element->biens()->exists() && !$element->lotissements()->exists();
    }
}
