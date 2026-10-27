<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\FraisType;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FraisTypeController extends CrudController
{
    protected string $modele = FraisType::class;

    protected string $route = 'fraistypes';

    protected string $titre = 'Frais annexes';

    protected string $titreSingulier = 'Frais';

    protected string $module = 'Paramétrage';

    protected array $recherche = ['libelle', 'code'];

    protected string $tri = 'activite';

    protected string $sens = 'asc';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'libelle', 'label' => 'Libellé', 'requis' => true],
            ['nom' => 'code', 'label' => 'Code', 'col' => 'col-md-3',
                'regles' => ['nullable', 'alpha_dash', 'max:50', Rule::unique('frais_types', 'code')->where('agence_id', agence()?->id)->ignore($element?->id)]],
            ['nom' => 'activite', 'label' => 'Activité', 'type' => 'select', 'options' => FraisType::ACTIVITES, 'requis' => true, 'col' => 'col-md-3'],
            ['nom' => 'mode_calcul', 'label' => 'Mode de calcul', 'type' => 'select', 'options' => ['fixe' => 'Montant fixe', 'pourcentage' => 'Pourcentage du prix / loyer'],
                'requis' => true, 'col' => 'col-md-4', 'defaut' => 'fixe'],
            ['nom' => 'montant', 'label' => 'Montant ou taux', 'type' => 'montant', 'requis' => true, 'col' => 'col-md-4'],
            ['nom' => 'obligatoire', 'label' => 'Ajouté automatiquement à chaque contrat', 'type' => 'checkbox', 'col' => 'col-md-4'],
            ['nom' => 'document_requis', 'label' => 'Donne lieu à un document à remettre (ACD, attestation…)', 'type' => 'checkbox', 'col' => 'col-md-8'],
            ['nom' => 'actif', 'label' => 'Actif', 'type' => 'checkbox', 'defaut' => true, 'col' => 'col-md-4'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Frais', fn ($f) => Cellule::deuxLignes($f->libelle, $f->code)],
            ['Activité', fn ($f) => FraisType::ACTIVITES[$f->activite] ?? $f->activite],
            ['Montant', fn ($f) => $f->mode_calcul === 'pourcentage' ? rtrim(rtrim((string) $f->montant, '0'), '.') . ' %' : Cellule::montant($f->montant), 'text-end'],
            ['Automatique', fn ($f) => Cellule::oui($f->obligatoire), 'text-center'],
            ['Document', fn ($f) => Cellule::oui($f->document_requis), 'text-center'],
            ['Statut', fn ($f) => Cellule::actif($f->actif)],
        ];
    }

    protected function filtres(): array
    {
        return [['nom' => 'activite', 'label' => 'Activité', 'options' => FraisType::ACTIVITES]];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->when($request->filled('activite'), fn ($q) => $q->where('activite', $request->activite));
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $donnees['code'] = strtoupper(($donnees['code'] ?? null) ?: ($element?->code ?? Str::slug($donnees['libelle'], '_')));

        return $donnees;
    }

    protected function supprimable(Model $element): bool
    {
        return !\App\Models\VenteFrais::where('frais_type_id', $element->id)->exists();
    }
}
