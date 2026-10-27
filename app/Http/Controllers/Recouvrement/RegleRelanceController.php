<?php

namespace App\Http\Controllers\Recouvrement;

use App\Http\Controllers\CrudController;
use App\Models\Recouvrement;
use App\Models\RegleRelance;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegleRelanceController extends CrudController
{
    protected string $modele = RegleRelance::class;

    protected string $route = 'reglesrelance';

    protected string $titre = 'Niveaux de relance';

    protected string $titreSingulier = 'Niveau de relance';

    protected string $module = 'Recouvrement';

    protected string $tri = 'niveau';

    protected string $sens = 'asc';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'niveau', 'label' => 'Niveau', 'type' => 'number', 'requis' => true, 'col' => 'col-md-2',
                'regles' => ['required', 'integer', 'min:1', 'max:20', Rule::unique('regles_relance', 'niveau')->where('agence_id', agence()?->id)->ignore($element?->id)]],
            ['nom' => 'libelle', 'label' => 'Libellé', 'requis' => true, 'col' => 'col-md-4'],
            ['nom' => 'delai_jours', 'label' => 'Jours de retard', 'type' => 'number', 'requis' => true, 'col' => 'col-md-2', 'regles' => ['required', 'integer', 'min:0', 'max:3650']],
            ['nom' => 'canal', 'label' => 'Canal', 'type' => 'select', 'options' => RegleRelance::CANAUX, 'requis' => true, 'col' => 'col-md-2'],
            ['nom' => 'automatique', 'label' => 'Envoi automatique', 'type' => 'checkbox', 'col' => 'col-md-2', 'defaut' => true],
            ['nom' => 'activites', 'label' => 'Activités concernées', 'type' => 'select', 'multiple' => true, 'options' => Recouvrement::ACTIVITES, 'col' => 'col-md-6',
                'aide' => 'Aucune sélection = toutes les activités.', 'regles' => ['nullable', 'array'], 'defaut' => $element?->activites ? explode(',', $element->activites) : []],
            ['nom' => 'objet', 'label' => 'Objet (e-mail, courrier)', 'col' => 'col-md-6'],
            ['nom' => 'message', 'label' => 'Message — variables : {nom} {montant} {retard} {details} {agence}', 'type' => 'textarea', 'requis' => true, 'rows' => 8],
            ['nom' => 'actif', 'label' => 'Actif', 'type' => 'checkbox', 'defaut' => true],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Niveau', fn ($r) => $r->niveau, 'text-center'],
            ['Relance', fn ($r) => Cellule::deuxLignes($r->libelle, $r->objet)],
            ['Déclenchement', fn ($r) => 'J+' . $r->delai_jours],
            ['Canal', fn ($r) => RegleRelance::CANAUX[$r->canal] ?? $r->canal],
            ['Activités', fn ($r) => $r->activites ? collect(explode(',', $r->activites))->map(fn ($a) => Recouvrement::ACTIVITES[$a] ?? $a)->implode(', ') : 'Toutes'],
            ['Auto.', fn ($r) => Cellule::oui($r->automatique), 'text-center'],
            ['Statut', fn ($r) => Cellule::actif($r->actif)],
        ];
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $donnees['activites'] = implode(',', $request->input('activites', [])) ?: null;

        return $donnees;
    }
}
