<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use App\Models\Concerns\AppartientAAgence;
use App\Models\Concerns\EstPayable;
use App\Models\Concerns\Payable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quote-part d'un lot dans un appel de fonds.
 */
class AppelLigne extends Model implements Payable
{
    use AppartientAAgence, EstPayable;

    protected $fillable = ['agence_id', 'appel_fonds_id', 'copro_lot_id', 'coproprietaire_id', 'tantiemes', 'montant', 'montant_paye', 'statut'];

    protected $attributes = ['statut' => 'a_payer', 'montant_paye' => 0];

    protected $casts = [
        'statut' => StatutPaiement::class,
        'tantiemes' => 'decimal:4',
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
    ];

    public function appel(): BelongsTo
    {
        return $this->belongsTo(AppelFonds::class, 'appel_fonds_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(CoproLot::class, 'copro_lot_id');
    }

    public function coproprietaire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'coproprietaire_id');
    }

    public function libelleAffectation(): string
    {
        return $this->appel?->libelle . ' — lot ' . $this->lot?->numero . ' (' . $this->appel?->numero . ')';
    }

    public function activite(): string
    {
        return 'syndic';
    }

    public function scopeEnRetard(Builder $query, $date = null): Builder
    {
        return $query->impayes()->whereHas('appel', fn ($q) => $q->whereDate('date_echeance', '<', $date ?? today()));
    }
}
