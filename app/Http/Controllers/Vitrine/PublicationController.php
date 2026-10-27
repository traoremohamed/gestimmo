<?php

namespace App\Http\Controllers\Vitrine;

use App\Http\Controllers\CrudController;
use App\Models\Publication;
use App\Models\Referentiel;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicationController extends CrudController
{
    protected string $modele = Publication::class;

    protected string $route = 'publications';

    protected string $titre = 'Actualités & articles';

    protected string $titreSingulier = 'Publication';

    protected string $module = 'Site vitrine';

    protected array $recherche = ['titre', 'resume'];

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'titre', 'label' => 'Titre', 'requis' => true, 'col' => 'col-md-8'],
            ['nom' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => Publication::TYPES, 'requis' => true, 'col' => 'col-md-2', 'defaut' => 'actualite'],
            ['nom' => 'categorie', 'label' => 'Catégorie', 'type' => 'select', 'options' => fn () => Referentiel::options('categorie_publication'), 'col' => 'col-md-2'],
            ['nom' => 'resume', 'label' => 'Résumé', 'type' => 'textarea', 'rows' => 2, 'regles' => ['nullable', 'string', 'max:500']],
            ['nom' => 'contenu', 'label' => 'Contenu', 'type' => 'textarea', 'rows' => 10],
            ['nom' => 'image', 'label' => 'Image', 'type' => 'image', 'col' => 'col-md-6'],
            ['nom' => 'video_url', 'label' => 'Lien vidéo (YouTube…)', 'type' => 'url', 'col' => 'col-md-6', 'regles' => ['nullable', 'url', 'max:255']],
            ['nom' => 'publie_le', 'label' => 'Publication le', 'type' => 'datetime', 'col' => 'col-md-4'],
            ['nom' => 'publie', 'label' => 'Publié', 'type' => 'checkbox', 'col' => 'col-md-4'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Titre', fn ($p) => Cellule::deuxLignes($p->titre, Publication::TYPES[$p->type] ?? $p->type)],
            ['Catégorie', fn ($p) => Referentiel::libelle('categorie_publication', $p->categorie)],
            ['Date', fn ($p) => ($p->publie_le ?? $p->created_at)->format('d/m/Y')],
            ['En ligne', fn ($p) => Cellule::oui($p->publie), 'text-center'],
        ];
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $slug = Str::slug($donnees['titre']);
        $i = 1;
        while (Publication::where('slug', $slug)->when($element, fn ($q) => $q->whereKeyNot($element->id))->exists()) {
            $slug = Str::slug($donnees['titre']) . '-' . ++$i;
        }

        return $donnees + ['slug' => $slug, 'auteur_id' => $element?->auteur_id ?? auth()->id()];
    }
}
