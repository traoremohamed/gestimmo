<?php

namespace App\Http\Controllers\Tiers;

use App\Enums\RoleTiers;
use App\Enums\StatutProspect;
use App\Http\Controllers\CrudController;
use App\Models\Programme;
use App\Models\Prospect;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Models\User;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Pipeline commercial. Un commercial ne voit que ses prospects, sauf permission prospects-tous.
 */
class ProspectController extends CrudController
{
    protected string $modele = Prospect::class;

    protected string $route = 'prospects';

    protected string $titre = 'Prospects';

    protected string $titreSingulier = 'Prospect';

    protected string $module = 'Tiers & CRM';

    protected array $with = ['tiers', 'commercial', 'programme'];

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'tiers_id', 'label' => 'Tiers', 'type' => 'select', 'requis' => true, 'recherche' => true, 'defaut' => request('tiers_id'),
                'options' => fn () => Tiers::orderBy('nom')->get()->pluck('nom_complet', 'id'), 'aide' => 'Créez d\'abord la fiche tiers si elle n\'existe pas.'],
            ['nom' => 'projet', 'label' => 'Projet', 'type' => 'select', 'options' => Prospect::PROJETS, 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'achat'],
            ['nom' => 'statut', 'label' => 'Étape', 'type' => 'select', 'options' => StatutProspect::options(), 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'nouveau'],
            ['nom' => 'type_bien', 'label' => 'Type de bien recherché', 'type' => 'select', 'options' => fn () => Referentiel::options('type_bien'), 'col' => 'col-md-4'],
            ['nom' => 'programme_id', 'label' => 'Programme d\'intérêt', 'type' => 'select', 'options' => fn () => Programme::orderBy('nom')->pluck('nom', 'id'), 'col' => 'col-md-4'],
            ['nom' => 'localisation', 'label' => 'Zone recherchée', 'col' => 'col-md-4'],
            ['nom' => 'budget_min', 'label' => 'Budget minimum', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'budget_max', 'label' => 'Budget maximum', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'superficie_min', 'label' => 'Superficie minimum (m²)', 'type' => 'number', 'col' => 'col-md-3'],
            ['nom' => 'date_relance', 'label' => 'Prochaine relance', 'type' => 'date', 'col' => 'col-md-3'],
            ['nom' => 'canal', 'label' => 'Canal', 'type' => 'select', 'options' => fn () => Referentiel::options('canal_acquisition'), 'col' => 'col-md-4'],
            ['nom' => 'commercial_id', 'label' => 'Commercial', 'type' => 'select', 'col' => 'col-md-4', 'defaut' => auth()->id(),
                'options' => fn () => User::where('agence_id', agence()?->id)->orderBy('name')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'commentaire', 'label' => 'Commentaire', 'type' => 'textarea'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Prospect', fn ($p) => Cellule::lien($p->tiers?->nom_complet, route('tiers.show', $p->tiers_id))],
            ['Projet', fn ($p) => Prospect::PROJETS[$p->projet] ?? $p->projet],
            ['Recherche', fn ($p) => collect([Referentiel::libelle('type_bien', $p->type_bien), $p->programme?->nom, $p->localisation])->filter()->implode(' · ')],
            ['Budget', fn ($p) => $p->budget_max ? Cellule::montant($p->budget_max) : '', 'text-end'],
            ['Relance', fn ($p) => $p->date_relance ? Cellule::badge($p->date_relance->format('d/m/Y'), $p->date_relance->isPast() ? 'danger' : 'light text-dark') : ''],
            ['Commercial', fn ($p) => $p->commercial?->nom_complet],
            ['Étape', fn ($p) => Cellule::statut($p->statut)],
        ];
    }

    protected function filtres(): array
    {
        return [
            ['nom' => 'statut', 'label' => 'Étape', 'options' => StatutProspect::options()],
            ['nom' => 'projet', 'label' => 'Projet', 'options' => Prospect::PROJETS],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $this->restreindre($query)
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('projet'), fn ($q) => $q->where('projet', $request->projet));
    }

    protected function trouver(string $id): Model
    {
        return $this->restreindre(Prospect::query()->with($this->with))->findOrFail($id);
    }

    protected function restreindre(Builder $query): Builder
    {
        return auth()->user()->can('prospects-tous') ? $query : $query->where('commercial_id', auth()->id());
    }

    protected function apresEnregistrement(Model $element, Request $request, bool $creation): void
    {
        $element->tiers?->ajouterRole(RoleTiers::Prospect);
    }
}
