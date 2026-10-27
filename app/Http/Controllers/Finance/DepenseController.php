<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\CrudController;
use App\Models\Bien;
use App\Models\Copropriete;
use App\Models\Depense;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Services\Numerotation;
use App\Services\Validation\ValidationService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DepenseController extends CrudController
{
    protected string $modele = Depense::class;

    protected string $route = 'depenses';

    protected string $titre = 'Dépenses';

    protected string $titreSingulier = 'Dépense';

    protected string $module = 'Caisse & finances';

    protected array $with = ['fournisseur', 'beneficiaire', 'bien', 'copropriete'];

    protected array $recherche = ['numero', 'libelle', 'reference'];

    protected string $tri = 'date_depense';

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'date_depense', 'label' => 'Date', 'type' => 'date', 'requis' => true, 'col' => 'col-md-3', 'defaut' => today()],
            ['nom' => 'categorie', 'label' => 'Catégorie', 'type' => 'select', 'requis' => true, 'col' => 'col-md-3',
                'options' => fn () => Referentiel::options('categorie_depense')->merge(['REMBOURSEMENT' => 'Remboursement client', 'REVERSEMENT' => 'Reversement propriétaire',
                    'RESTITUTION' => 'Restitution des garanties (sortie locataire)', 'COMPTEUR_CIE' => 'Crédit compteur CIE remboursé'])],
            ['nom' => 'libelle', 'label' => 'Libellé', 'requis' => true],
            ['nom' => 'montant', 'label' => 'Montant', 'type' => 'montant', 'requis' => true, 'col' => 'col-md-3'],
            ['nom' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => Depense::STATUTS, 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'payee'],
            ['nom' => 'mode_paiement', 'label' => 'Mode de paiement', 'type' => 'select', 'options' => fn () => Referentiel::options('mode_paiement'), 'col' => 'col-md-3'],
            ['nom' => 'reference', 'label' => 'Référence', 'col' => 'col-md-3'],
            ['nom' => 'fournisseur_id', 'label' => 'Fournisseur', 'type' => 'select', 'recherche' => true, 'col' => 'col-md-6',
                'options' => fn () => Tiers::avecRole('fournisseur')->orderBy('nom')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'copropriete_id', 'label' => 'Copropriété (charges du syndic)', 'type' => 'select', 'col' => 'col-md-6',
                'options' => fn () => Copropriete::orderBy('nom')->pluck('nom', 'id')],
            ['nom' => 'bien_id', 'label' => 'Bien concerné', 'type' => 'select', 'recherche' => true, 'col' => 'col-md-6',
                'options' => fn () => Bien::orderBy('reference')->get()->pluck('designation', 'id')],
            ['nom' => 'refacturable_proprietaire', 'label' => 'À déduire du reversement au propriétaire', 'type' => 'checkbox', 'col' => 'col-md-6'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Date', fn ($d) => Cellule::deuxLignes($d->date_depense->format('d/m/Y'), $d->numero)],
            ['Libellé', fn ($d) => Cellule::deuxLignes($d->libelle, Referentiel::libelle('categorie_depense', $d->categorie))],
            ['Imputation', fn ($d) => $d->copropriete?->nom ?? $d->bien?->reference ?? $d->beneficiaire?->nom_complet],
            ['Fournisseur', fn ($d) => $d->fournisseur?->nom_complet],
            ['Montant', fn ($d) => Cellule::montant($d->montant), 'text-end'],
            ['Statut', fn ($d) => Cellule::badge(Depense::STATUTS[$d->statut] ?? $d->statut, ['payee' => 'success', 'a_payer' => 'warning', 'annulee' => 'secondary'][$d->statut] ?? 'secondary')],
        ];
    }

    protected function filtres(): array
    {
        return [
            ['nom' => 'categorie', 'label' => 'Catégorie', 'options' => fn () => Referentiel::options('categorie_depense')],
            ['nom' => 'copropriete_id', 'label' => 'Copropriété', 'options' => fn () => Copropriete::orderBy('nom')->pluck('nom', 'id')],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->when($request->filled('categorie'), fn ($q) => $q->where('categorie', $request->categorie))
            ->when($request->filled('copropriete_id'), fn ($q) => $q->where('copropriete_id', $request->copropriete_id));
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        if (!$element) {
            $donnees['numero'] = app(Numerotation::class)->suivant('depense');
            $donnees['created_by'] = auth()->id();
        } elseif ($element->reversement_id) {
            // une dépense déjà déduite d'un reversement ne change plus de montant
            unset($donnees['montant'], $donnees['refacturable_proprietaire'], $donnees['bien_id']);
        }

        // circuit de validation du paiement : la dépense reste « à payer » (non comptabilisée) jusqu'à l'approbation
        $this->paiementASoumettre = ($donnees['statut'] ?? null) === 'payee' && $element?->statut !== 'payee'
            && ValidationService::soumise('DEPENSE_PAIEMENT', null, (float) ($donnees['montant'] ?? $element?->montant), $donnees['categorie'] ?? $element?->categorie);
        if ($this->paiementASoumettre) {
            $donnees['statut'] = 'a_payer';
        }

        return $donnees;
    }

    protected bool $paiementASoumettre = false;

    protected function apresEnregistrement(Model $element, Request $request, bool $creation): void
    {
        parent::apresEnregistrement($element, $request, $creation);
        if ($this->paiementASoumettre && ($demande = ValidationService::intercepter('DEPENSE_PAIEMENT', $element, [], null, null, $element->categorie))) {
            session()->flash('info', 'Dépense ' . $element->numero . ' enregistrée « à payer » : son paiement est soumis à validation (demande ' . $demande->numero . ').');
        }
    }
}
