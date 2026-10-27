<?php

namespace App\Http\Controllers\Pilotage;

use App\Http\Controllers\CrudController;
use App\Models\Objectif;
use App\Models\User;
use App\Models\Vente;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Objectifs de chiffre d'affaires par commercial (ancienne « attribution des chiffres d'affaires »).
 */
class ObjectifController extends CrudController
{
    protected string $modele = Objectif::class;

    protected string $route = 'objectifs';

    protected string $titre = 'Objectifs commerciaux';

    protected string $titreSingulier = 'Objectif';

    protected string $module = 'Tableaux de bord';

    protected array $with = ['commercial'];

    protected string $tri = 'periode_debut';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'user_id', 'label' => 'Commercial', 'type' => 'select', 'requis' => true,
                'options' => fn () => User::where('agence_id', agence()?->id)->orderBy('name')->get()->pluck('nom_complet', 'id'),
                'regles' => ['required', Rule::exists('users', 'id')->where('agence_id', agence()?->id)]],
            ['nom' => 'activite', 'label' => 'Activité', 'type' => 'select', 'options' => ['vente' => 'Vente', 'location' => 'Location'], 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'vente'],
            ['nom' => 'montant_objectif', 'label' => 'Objectif', 'type' => 'montant', 'requis' => true, 'col' => 'col-md-3'],
            ['nom' => 'periode_debut', 'label' => 'Du', 'type' => 'date', 'requis' => true, 'col' => 'col-md-3', 'defaut' => today()->startOfMonth()],
            ['nom' => 'periode_fin', 'label' => 'Au', 'type' => 'date', 'requis' => true, 'col' => 'col-md-3', 'defaut' => today()->endOfMonth(),
                'regles' => ['required', 'date', 'after_or_equal:periode_debut']],
            ['nom' => 'commentaire', 'label' => 'Commentaire', 'type' => 'textarea'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Commercial', fn ($o) => $o->commercial?->nom_complet],
            ['Période', fn ($o) => $o->periode_debut->format('d/m/Y') . ' → ' . $o->periode_fin->format('d/m/Y')],
            ['Objectif', fn ($o) => Cellule::montant($o->montant_objectif), 'text-end'],
            ['Réalisé', fn ($o) => Cellule::montant($this->realise($o)), 'text-end'],
            ['Taux', function ($o) {
                $taux = (float) $o->montant_objectif > 0 ? round($this->realise($o) / (float) $o->montant_objectif * 100) : 0;

                return Cellule::badge($taux . ' %', $taux >= 100 ? 'success' : ($taux >= 70 ? 'warning' : 'danger'));
            }, 'text-end'],
        ];
    }

    protected function realise(Objectif $objectif): float
    {
        return $objectif->realise ??= (float) Vente::where('commercial_id', $objectif->user_id)->whereNotNull('valide_le')
            ->whereBetween('date_contrat', [$objectif->periode_debut, $objectif->periode_fin])->sum('montant_net');
    }
}
