<?php

namespace App\Http\Controllers\Patrimoine;

use App\Http\Controllers\CrudController;
use App\Models\Lotissement;
use App\Models\Programme;
use App\Models\Referentiel;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LotissementController extends CrudController
{
    protected string $modele = Lotissement::class;

    protected string $route = 'lotissements';

    protected string $titre = 'Lotissements';

    protected string $titreSingulier = 'Lotissement';

    protected string $module = 'Patrimoine';

    protected array $with = ['programme'];

    protected array $recherche = ['nom', 'reference', 'numero_titre', 'village'];

    protected string $tri = 'nom';

    protected string $sens = 'asc';

    protected ?string $vueFiche = 'patrimoine.lotissement';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'nom', 'label' => 'Nom', 'requis' => true],
            ['nom' => 'programme_id', 'label' => 'Programme', 'type' => 'select', 'options' => fn () => Programme::orderBy('nom')->pluck('nom', 'id')],
            ['nom' => 'reference', 'label' => 'Référence', 'col' => 'col-md-4'],
            ['nom' => 'type_titre', 'label' => 'Type de titre foncier', 'type' => 'select', 'options' => fn () => Referentiel::options('type_titre_foncier'), 'col' => 'col-md-4'],
            ['nom' => 'numero_titre', 'label' => 'Numéro du titre', 'col' => 'col-md-4'],
            ['nom' => 'prefecture', 'label' => 'Préfecture / sous-préfecture', 'col' => 'col-md-4'],
            ['nom' => 'village', 'label' => 'Village', 'col' => 'col-md-4'],
            ['nom' => 'superficie', 'label' => 'Superficie totale (m²)', 'type' => 'number', 'col' => 'col-md-4'],
            ['nom' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'between:-90,90']],
            ['nom' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'between:-180,180']],
            ['nom' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['nom' => 'actif', 'label' => 'Actif', 'type' => 'checkbox', 'defaut' => true],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Lotissement', fn ($l) => Cellule::deuxLignes($l->nom, $l->reference)],
            ['Programme', fn ($l) => $l->programme?->nom],
            ['Titre', fn ($l) => collect([$l->type_titre, $l->numero_titre])->filter()->implode(' n° ')],
            ['Lots', fn ($l) => $l->biens_count, 'text-end'],
            ['Disponibles', fn ($l) => $l->disponibles_count, 'text-end'],
            ['Statut', fn ($l) => Cellule::actif($l->actif)],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->withCount(['biens', 'biens as disponibles_count' => fn ($q) => $q->where('statut', 'disponible')])
            ->when($request->filled('programme_id'), fn ($q) => $q->where('programme_id', $request->programme_id));
    }

    protected function filtres(): array
    {
        return [['nom' => 'programme_id', 'label' => 'Programme', 'options' => fn () => Programme::orderBy('nom')->pluck('nom', 'id')]];
    }

    protected function supprimable(Model $element): bool
    {
        return !$element->biens()->exists();
    }
}
