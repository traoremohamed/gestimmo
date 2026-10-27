<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\CrudController;
use App\Models\Rh\Rubrique;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rubriques de paie : taux, plafonds, barème de l'ITS (JSON), comptes comptables.
 */
class RubriqueController extends CrudController
{
    protected string $modele = Rubrique::class;

    protected string $route = 'rubriques';

    protected string $titre = 'Rubriques de paie';

    protected string $titreSingulier = 'Rubrique';

    protected string $module = 'Paie';

    protected array $recherche = ['code', 'libelle'];

    protected string $tri = 'ordre';

    protected string $sens = 'asc';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'code', 'label' => 'Code', 'requis' => true, 'col' => 'col-md-2',
                'regles' => ['required', 'alpha_dash', 'max:20', Rule::unique('rh_rubriques', 'code')->where('agence_id', agence()?->id)->ignore($element?->id)]],
            ['nom' => 'numero', 'label' => 'N° imprimé', 'col' => 'col-md-2'],
            ['nom' => 'libelle', 'label' => 'Libellé', 'requis' => true, 'col' => 'col-md-5'],
            ['nom' => 'ordre', 'label' => 'Ordre de calcul', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['required', 'integer', 'min:0']],
            ['nom' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => Rubrique::TYPES, 'requis' => true, 'col' => 'col-md-4'],
            ['nom' => 'mode_calcul', 'label' => 'Mode de calcul', 'type' => 'select', 'options' => Rubrique::MODES, 'requis' => true, 'col' => 'col-md-4'],
            ['nom' => 'base', 'label' => 'Base', 'type' => 'select', 'options' => Rubrique::BASES, 'col' => 'col-md-4'],
            ['nom' => 'taux', 'label' => 'Taux (%)', 'type' => 'number', 'col' => 'col-md-3'],
            ['nom' => 'montant', 'label' => 'Montant', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'plafond', 'label' => 'Plafond de la base', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'franchise', 'label' => 'Franchise non imposable', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'anciennete_min_ans', 'label' => 'Ancienneté minimale (ans)', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'integer', 'min:0']],
            ['nom' => 'groupe', 'label' => 'Groupe sur le bulletin', 'type' => 'select', 'options' => ['GAINS' => 'Gains', 'NON_IMPOSABLES' => 'Non imposables', 'RETENUES' => 'Retenues', 'AUTRES' => 'Autres', 'PATRONAL' => 'Charges patronales'], 'col' => 'col-md-3'],
            ['nom' => 'compte_charge', 'label' => 'Compte de charge', 'col' => 'col-md-3'],
            ['nom' => 'compte_tiers', 'label' => 'Compte de tiers', 'col' => 'col-md-3'],
            ['nom' => 'imposable', 'label' => 'Imposable (ITS)', 'type' => 'checkbox', 'col' => 'col-md-3', 'defaut' => true],
            ['nom' => 'cotisable', 'label' => 'Soumis à cotisations', 'type' => 'checkbox', 'col' => 'col-md-3', 'defaut' => true],
            ['nom' => 'proratiser', 'label' => 'Proratiser selon la présence', 'type' => 'checkbox', 'col' => 'col-md-3'],
            ['nom' => 'actif', 'label' => 'Active', 'type' => 'checkbox', 'col' => 'col-md-3', 'defaut' => true],
            ['nom' => 'bareme_texte', 'label' => 'Barème [[de, à, taux], …] (mode Barème)', 'type' => 'textarea', 'rows' => 2, 'defaut' => $element?->bareme ? json_encode($element->bareme) : null,
                'regles' => ['nullable', 'json']],
            ['nom' => 'reduction_texte', 'label' => 'Réduction par parts {"1": 0, "1.5": 5500, …}', 'type' => 'textarea', 'rows' => 2,
                'defaut' => $element?->reduction_parts ? json_encode($element->reduction_parts) : null, 'regles' => ['nullable', 'json']],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Ordre', fn ($r) => $r->ordre, 'text-end'],
            ['Rubrique', fn ($r) => Cellule::deuxLignes($r->code . ' — ' . $r->libelle, Rubrique::MODES[$r->mode_calcul] ?? $r->mode_calcul)],
            ['Type', fn ($r) => Rubrique::TYPES[$r->type] ?? $r->type],
            ['Taux / montant', fn ($r) => $r->taux !== null ? (float) $r->taux . ' %' : ($r->montant !== null ? montant($r->montant) : ($r->bareme ? 'Barème' : '—')), 'text-end'],
            ['Plafond', fn ($r) => $r->plafond ? montant($r->plafond) : '', 'text-end'],
            ['Comptes', fn ($r) => trim($r->compte_charge . ' ' . $r->compte_tiers)],
            ['Statut', fn ($r) => Cellule::actif($r->actif)],
        ];
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $donnees['bareme'] = $request->filled('bareme_texte') ? json_decode($request->bareme_texte, true) : null;
        $donnees['reduction_parts'] = $request->filled('reduction_texte') ? json_decode($request->reduction_texte, true) : null;
        unset($donnees['bareme_texte'], $donnees['reduction_texte']);

        return $donnees;
    }
}
