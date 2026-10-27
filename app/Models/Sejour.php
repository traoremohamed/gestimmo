<?php

namespace App\Models;

use App\Enums\StatutSejour;
use App\Models\Concerns\AppartientAAgence;
use App\Models\Concerns\EstPayable;
use App\Models\Concerns\Payable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Séjour en résidence meublée (remplace reservation_commande / reservation_facture).
 */
class Sejour extends Model implements Payable
{
    use AppartientAAgence, EstPayable;

    protected $fillable = [
        'agence_id', 'numero', 'bien_id', 'tiers_id', 'date_arrivee', 'date_depart', 'nb_nuits', 'nb_personnes', 'prix_nuitee', 'remise',
        'frais_supplementaires', 'montant', 'montant_paye', 'statut', 'source', 'observation', 'created_by', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'reserve', 'montant_paye' => 0];

    protected $casts = [
        'statut' => StatutSejour::class,
        'date_arrivee' => 'date',
        'date_depart' => 'date',
        'prix_nuitee' => 'decimal:2',
        'remise' => 'decimal:2',
        'frais_supplementaires' => 'decimal:2',
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
    ];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'tiers_id');
    }

    public function libelleAffectation(): string
    {
        return 'Séjour ' . $this->numero . ' (' . $this->date_arrivee?->format('d/m/Y') . ' – ' . $this->date_depart?->format('d/m/Y') . ')';
    }

    public function activite(): string
    {
        return 'meuble';
    }

    // le statut du séjour est son cycle de vie, pas un statut de paiement
    protected function colonneStatutPaiement(): ?string
    {
        return null;
    }

    public function scopeImpayes(Builder $query): Builder
    {
        return $query->where('statut', '!=', StatutSejour::Annule->value)->whereColumn('montant_paye', '<', 'montant');
    }

    protected function colonneDateEcheance(): string
    {
        return 'date_arrivee';
    }
}
