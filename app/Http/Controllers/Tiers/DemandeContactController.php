<?php

namespace App\Http\Controllers\Tiers;

use App\Http\Controllers\CrudController;
use App\Models\DemandeContact;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Messages reçus du site vitrine : consultation et traitement.
 */
class DemandeContactController extends CrudController
{
    protected string $modele = DemandeContact::class;

    protected string $route = 'demandescontact';

    protected string $titre = 'Demandes de contact';

    protected string $titreSingulier = 'Demande';

    protected string $module = 'Tiers & CRM';

    protected array $with = ['bien', 'traitePar'];

    protected array $recherche = ['nom', 'email', 'telephone', 'sujet', 'message'];

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => DemandeContact::STATUTS, 'requis' => true, 'col' => 'col-md-4'],
            ['nom' => 'reponse', 'label' => 'Réponse / suite donnée', 'type' => 'textarea', 'rows' => 4],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Reçue le', fn ($d) => $d->created_at->format('d/m/Y H:i')],
            ['Contact', fn ($d) => Cellule::deuxLignes($d->nom, collect([$d->telephone, $d->email])->filter()->implode(' · '))],
            ['Message', fn ($d) => Cellule::deuxLignes($d->sujet ?: ($d->bien ? 'Bien ' . $d->bien->reference : 'Contact'), \Illuminate\Support\Str::limit($d->message, 90))],
            ['Statut', fn ($d) => Cellule::badge(DemandeContact::STATUTS[$d->statut] ?? $d->statut, $d->statut === 'nouvelle' ? 'danger' : 'secondary')],
        ];
    }

    protected function filtres(): array
    {
        return [['nom' => 'statut', 'label' => 'Statut', 'options' => DemandeContact::STATUTS]];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut));
    }

    public function edit(string $id)
    {
        $demande = $this->trouver($id);

        return view('tiers.demande', $this->donneesVue() + ['element' => $demande, 'champs' => $this->champs($demande)]);
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        return $donnees + ['traite_par' => auth()->id(), 'traite_le' => now()];
    }
}
