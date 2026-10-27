<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use App\Models\Concerns\AppartientAAgence;
use App\Models\Concerns\EstPayable;
use App\Models\Concerns\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Échéance d'un contrat de vente (numéro 0 = acompte).
 */
class Echeance extends Model implements Payable
{
    use AppartientAAgence, EstPayable;

    protected $fillable = ['agence_id', 'vente_id', 'numero', 'libelle', 'date_echeance', 'montant', 'montant_paye', 'statut'];

    protected $attributes = ['statut' => 'a_payer', 'montant_paye' => 0];

    protected $casts = [
        'statut' => StatutPaiement::class,
        'date_echeance' => 'date',
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function libelleAffectation(): string
    {
        return $this->libelle . ' — ' . $this->vente?->numero;
    }

    public function activite(): string
    {
        return 'vente';
    }

    public function estEnRetard(): bool
    {
        return !$this->estSolde() && $this->statut !== StatutPaiement::Annule && $this->date_echeance->isPast();
    }
}
