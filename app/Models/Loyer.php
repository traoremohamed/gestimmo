<?php

namespace App\Models;

use App\Enums\NatureLoyer;
use App\Enums\StatutPaiement;
use App\Models\Concerns\AppartientAAgence;
use App\Models\Concerns\EstPayable;
use App\Models\Concerns\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis d'échéance locatif : loyer d'une période, dépôt de garantie, avance,
 * frais d'agence ou pénalité. Une fois soldé, il donne lieu à une quittance.
 */
class Loyer extends Model implements Payable
{
    use AppartientAAgence, EstPayable;

    protected $fillable = [
        'agence_id', 'bail_id', 'nature', 'periode_debut', 'periode_fin', 'libelle', 'date_echeance', 'montant_loyer', 'montant_charges',
        'montant', 'montant_paye', 'statut', 'quittance_numero', 'reverse',
    ];

    protected $attributes = ['statut' => 'a_payer', 'montant_paye' => 0, 'reverse' => false];

    protected $casts = [
        'nature' => NatureLoyer::class,
        'statut' => StatutPaiement::class,
        'periode_debut' => 'date',
        'periode_fin' => 'date',
        'date_echeance' => 'date',
        'montant_loyer' => 'decimal:2',
        'montant_charges' => 'decimal:2',
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'reverse' => 'boolean',
    ];

    public function bail(): BelongsTo
    {
        return $this->belongsTo(Bail::class);
    }

    public function libelleAffectation(): string
    {
        return $this->libelle . ' — ' . $this->bail?->numero;
    }

    public function activite(): string
    {
        return 'location';
    }

    /**
     * Revient au propriétaire (loyers, charges, pénalités) ; le dépôt de garantie
     * est conservé par l'agence et les frais d'agence lui appartiennent.
     */
    public function estReversable(): bool
    {
        return in_array($this->nature, [NatureLoyer::Loyer, NatureLoyer::Avance, NatureLoyer::Penalite, NatureLoyer::Regularisation], true);
    }

    public function estEnRetard(): bool
    {
        return !$this->estSolde() && $this->statut !== StatutPaiement::Annule && $this->date_echeance->isPast();
    }
}
