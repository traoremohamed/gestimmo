<?php

namespace App\Http\Controllers\Vitrine;

use App\Http\Controllers\CrudController;
use App\Models\Slide;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SlideController extends CrudController
{
    protected string $modele = Slide::class;

    protected string $route = 'slides';

    protected string $titre = 'Slides de la page d\'accueil';

    protected string $titreSingulier = 'Slide';

    protected string $module = 'Site vitrine';

    protected string $tri = 'ordre';

    protected string $sens = 'asc';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'titre', 'label' => 'Titre', 'requis' => true],
            ['nom' => 'sous_titre', 'label' => 'Sous-titre'],
            ['nom' => 'image', 'label' => 'Image (1920 × 800 conseillé)', 'type' => 'image', 'requis' => !$element],
            ['nom' => 'video_url', 'label' => 'Ou lien vidéo', 'type' => 'url', 'regles' => ['nullable', 'url', 'max:255']],
            ['nom' => 'bouton_libelle', 'label' => 'Texte du bouton', 'col' => 'col-md-4'],
            ['nom' => 'bouton_lien', 'label' => 'Lien du bouton', 'col' => 'col-md-4', 'regles' => ['nullable', 'string', 'max:255']],
            ['nom' => 'ordre', 'label' => 'Ordre', 'type' => 'number', 'col' => 'col-md-2', 'defaut' => 0, 'regles' => ['nullable', 'integer']],
            ['nom' => 'actif', 'label' => 'Actif', 'type' => 'checkbox', 'col' => 'col-md-2', 'defaut' => true],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Image', fn ($s) => $s->image ? new HtmlString('<img src="' . e(asset('storage/' . $s->image)) . '" alt="" style="height: 40px" class="rounded">') : ''],
            ['Titre', fn ($s) => Cellule::deuxLignes($s->titre, $s->sous_titre)],
            ['Ordre', fn ($s) => $s->ordre, 'text-end'],
            ['Statut', fn ($s) => Cellule::actif($s->actif)],
        ];
    }
}
