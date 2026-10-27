<?php

namespace App\Http\Controllers\Vitrine;

use App\Http\Controllers\CrudController;
use App\Models\Temoignage;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;

class TemoignageController extends CrudController
{
    protected string $modele = Temoignage::class;

    protected string $route = 'temoignages';

    protected string $titre = 'Témoignages';

    protected string $titreSingulier = 'Témoignage';

    protected string $module = 'Site vitrine';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'auteur', 'label' => 'Auteur', 'requis' => true],
            ['nom' => 'fonction', 'label' => 'Fonction / qualité', 'col' => 'col-md-4'],
            ['nom' => 'note', 'label' => 'Note', 'type' => 'select', 'options' => [5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★'], 'col' => 'col-md-2', 'defaut' => 5, 'requis' => true],
            ['nom' => 'contenu', 'label' => 'Témoignage', 'type' => 'textarea', 'requis' => true],
            ['nom' => 'photo', 'label' => 'Photo', 'type' => 'image', 'col' => 'col-md-6'],
            ['nom' => 'actif', 'label' => 'Affiché', 'type' => 'checkbox', 'col' => 'col-md-6', 'defaut' => true],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Auteur', fn ($t) => Cellule::deuxLignes($t->auteur, $t->fonction)],
            ['Témoignage', fn ($t) => \Illuminate\Support\Str::limit($t->contenu, 100)],
            ['Note', fn ($t) => str_repeat('★', $t->note)],
            ['Statut', fn ($t) => Cellule::actif($t->actif)],
        ];
    }
}
