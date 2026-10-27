<?php

namespace App\Models\Concerns;

use App\Enums\StatutPaiement;
use App\Models\EncaissementAffectation;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Dette pouvant recevoir des encaissements (échéance, frais, loyer, appel de fonds, séjour).
 * Colonnes attendues : montant, montant_paye et, sauf surcharge de colonneStatutPaiement(), statut.
 *
 * Le reste à payer est toujours calculé côté serveur : un formulaire ne fixe jamais
 * le montant d'une dette (l'ancienne application acceptait le montant envoyé par le navigateur).
 */
trait EstPayable
{
    public function affectations(): MorphMany
    {
        return $this->morphMany(EncaissementAffectation::class, 'affectable');
    }

    public function montantDu(): string
    {
        return Montant::normaliser($this->montant);
    }

    public function resteAPayer(): string
    {
        $reste = Montant::sub($this->montantDu(), $this->montant_paye);

        return Montant::compare($reste, 0) > 0 ? $reste : '0.00';
    }

    public function estSolde(): bool
    {
        return !Montant::estPositif($this->resteAPayer());
    }

    public function imputer(string $montant): void
    {
        $this->montant_paye = Montant::add($this->montant_paye, $montant);
        $this->majStatutPaiement();
        $this->save();
    }

    public function desimputer(string $montant): void
    {
        $paye = Montant::sub($this->montant_paye, $montant);
        $this->montant_paye = Montant::compare($paye, 0) > 0 ? $paye : '0.00';
        $this->majStatutPaiement();
        $this->save();
    }

    protected function majStatutPaiement(): void
    {
        $colonne = $this->colonneStatutPaiement();
        if ($colonne === null || $this->{$colonne} === StatutPaiement::Annule) {
            return;
        }

        $this->{$colonne} = match (true) {
            $this->estSolde() => StatutPaiement::Paye,
            Montant::estPositif($this->montant_paye) => StatutPaiement::Partiel,
            default => StatutPaiement::APayer,
        };
    }

    protected function colonneStatutPaiement(): ?string
    {
        return 'statut';
    }

    public function scopeImpayes(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('statut'), [StatutPaiement::APayer->value, StatutPaiement::Partiel->value]);
    }

    public function scopeEnRetard(Builder $query, $date = null): Builder
    {
        return $query->impayes()->whereDate($this->qualifyColumn($this->colonneDateEcheance()), '<', $date ?? today());
    }

    protected function colonneDateEcheance(): string
    {
        return 'date_echeance';
    }

    /**
     * Libellé affiché sur le reçu (ex : « Échéance 3/12 — CV-2026-00004 »).
     */
    abstract public function libelleAffectation(): string;

    /**
     * Activité comptable : vente | location | syndic | meuble.
     */
    abstract public function activite(): string;
}
