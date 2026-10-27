<?php

namespace App\Http\Controllers\Location;

use App\Enums\RoleTiers;
use App\Http\Controllers\CrudController;
use App\Models\Bien;
use App\Models\Mandat;
use App\Models\Tiers;
use App\Services\Numerotation;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Mandats de gestion : biens confiés par un propriétaire, taux de commission.
 */
class MandatController extends CrudController
{
    protected string $modele = Mandat::class;

    protected string $route = 'mandats';

    protected string $titre = 'Mandats de gestion';

    protected string $titreSingulier = 'Mandat';

    protected string $module = 'Gestion locative';

    protected array $with = ['proprietaire'];

    protected array $recherche = ['numero'];

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'proprietaire_id', 'label' => 'Propriétaire', 'type' => 'select', 'requis' => true, 'recherche' => true,
                'options' => fn () => Tiers::orderBy('nom')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => Mandat::STATUTS, 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'actif'],
            ['nom' => 'taux_commission', 'label' => 'Commission (%)', 'type' => 'number', 'requis' => true, 'col' => 'col-md-3',
                'defaut' => parametre('location.taux_commission_gestion'), 'regles' => ['required', 'numeric', 'min:0', 'max:100']],
            ['nom' => 'date_debut', 'label' => 'Début', 'type' => 'date', 'requis' => true, 'col' => 'col-md-3', 'defaut' => today()],
            ['nom' => 'date_fin', 'label' => 'Fin', 'type' => 'date', 'col' => 'col-md-3', 'regles' => ['nullable', 'date', 'after:date_debut']],
            ['nom' => 'jour_reversement', 'label' => 'Jour de reversement', 'type' => 'number', 'col' => 'col-md-3', 'defaut' => 10, 'regles' => ['required', 'integer', 'min:1', 'max:28']],
            ['nom' => 'commission_sur_charges', 'label' => 'Commission calculée aussi sur les charges', 'type' => 'checkbox', 'col' => 'col-md-3'],
            ['nom' => 'biens', 'label' => 'Biens confiés', 'type' => 'select', 'multiple' => true, 'col' => 'col-12', 'regles' => ['nullable', 'array'],
                'defaut' => $element?->biens()->pluck('id')->all() ?? [],
                'options' => fn () => Bien::where('a_louer', true)->where(fn ($q) => $q->whereNull('mandat_id')->orWhere('mandat_id', $element?->id))
                    ->orderBy('reference')->get()->pluck('designation', 'id')],
            ['nom' => 'conditions', 'label' => 'Conditions particulières', 'type' => 'textarea'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Mandat', fn ($m) => Cellule::deuxLignes($m->numero, 'depuis le ' . $m->date_debut->format('d/m/Y'))],
            ['Propriétaire', fn ($m) => Cellule::lien($m->proprietaire?->nom_complet, route('tiers.show', $m->proprietaire_id))],
            ['Biens', fn ($m) => $m->biens_count, 'text-end'],
            ['Commission', fn ($m) => rtrim(rtrim((string) $m->taux_commission, '0'), '.') . ' %', 'text-end'],
            ['Statut', fn ($m) => Cellule::badge(Mandat::STATUTS[$m->statut] ?? $m->statut, $m->statut === 'actif' ? 'success' : 'secondary')],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->withCount('biens');
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        unset($donnees['biens']);
        if (!$element) {
            $donnees['numero'] = app(Numerotation::class)->suivant('mandat');
        }

        return $donnees;
    }

    protected function apresEnregistrement(Model $element, Request $request, bool $creation): void
    {
        $biens = array_map('intval', $request->input('biens', []));

        Bien::where('mandat_id', $element->id)->whereNotIn('id', $biens)->update(['mandat_id' => null]);
        Bien::whereIn('id', $biens)->update(['mandat_id' => $element->id, 'proprietaire_id' => $element->proprietaire_id]);

        $element->proprietaire?->ajouterRole(RoleTiers::Proprietaire);
    }
}
