<?php

namespace App\Http\Controllers\Tiers;

use App\Enums\StatutReclamation;
use App\Http\Controllers\CrudController;
use App\Models\Bien;
use App\Models\Reclamation;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Models\User;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ReclamationController extends CrudController
{
    protected string $modele = Reclamation::class;

    protected string $route = 'reclamations';

    protected string $titre = 'Réclamations';

    protected string $titreSingulier = 'Réclamation';

    protected string $module = 'Tiers & CRM';

    protected array $with = ['tiers', 'bien', 'responsable'];

    protected array $recherche = ['objet', 'message'];

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'tiers_id', 'label' => 'Client / locataire / copropriétaire', 'type' => 'select', 'recherche' => true,
                'options' => fn () => Tiers::orderBy('nom')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'bien_id', 'label' => 'Bien concerné', 'type' => 'select', 'recherche' => true, 'options' => fn () => Bien::orderBy('reference')->get()->pluck('designation', 'id')],
            ['nom' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => fn () => Referentiel::options('type_reclamation'), 'col' => 'col-md-4'],
            ['nom' => 'priorite', 'label' => 'Priorité', 'type' => 'select', 'options' => Reclamation::PRIORITES, 'requis' => true, 'col' => 'col-md-4', 'defaut' => 'normale'],
            ['nom' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => StatutReclamation::options(), 'requis' => true, 'col' => 'col-md-4', 'defaut' => 'ouverte'],
            ['nom' => 'objet', 'label' => 'Objet', 'requis' => true, 'col' => 'col-md-8'],
            ['nom' => 'assigne_a', 'label' => 'Responsable', 'type' => 'select', 'col' => 'col-md-4',
                'options' => fn () => User::where('agence_id', agence()?->id)->orderBy('name')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'message', 'label' => 'Description', 'type' => 'textarea', 'requis' => true],
            ['nom' => 'resolution', 'label' => 'Résolution', 'type' => 'textarea'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Date', fn ($r) => $r->created_at->format('d/m/Y')],
            ['Objet', fn ($r) => Cellule::deuxLignes($r->objet, Referentiel::libelle('type_reclamation', $r->type))],
            ['Tiers', fn ($r) => $r->tiers?->nom_complet],
            ['Bien', fn ($r) => $r->bien?->reference],
            ['Priorité', fn ($r) => Cellule::badge(Reclamation::PRIORITES[$r->priorite] ?? $r->priorite, ['urgente' => 'danger', 'haute' => 'warning'][$r->priorite] ?? 'light text-dark')],
            ['Responsable', fn ($r) => $r->responsable?->nom_complet],
            ['Statut', fn ($r) => Cellule::statut($r->statut)],
        ];
    }

    protected function filtres(): array
    {
        return [
            ['nom' => 'statut', 'label' => 'Statut', 'options' => StatutReclamation::options()],
            ['nom' => 'priorite', 'label' => 'Priorité', 'options' => Reclamation::PRIORITES],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('priorite'), fn ($q) => $q->where('priorite', $request->priorite));
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $resolue = in_array($donnees['statut'] ?? null, [StatutReclamation::Resolue->value, StatutReclamation::Fermee->value], true);
        $donnees['resolue_le'] = $resolue ? ($element?->resolue_le ?? now()) : null;
        if (!$element) {
            $donnees['created_by'] = auth()->id();
        }

        return $donnees;
    }
}
