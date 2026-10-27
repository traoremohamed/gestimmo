<?php

namespace App\Models;

use App\Enums\StatutReversement;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reversement au propriétaire : loyers encaissés - commission de gestion - dépenses refacturées.
 */
class Reversement extends Model
{
    use AppartientAAgence;

    protected $fillable = [
        'agence_id', 'numero', 'proprietaire_id', 'mandat_id', 'periode_debut', 'periode_fin', 'montant_encaisse', 'montant_commission',
        'montant_deductions', 'montant_net', 'statut', 'mode_paiement', 'reference_paiement', 'paye_le', 'observation', 'created_by',
    ];

    protected $attributes = ['statut' => 'brouillon'];

    protected $casts = [
        'statut' => StatutReversement::class,
        'periode_debut' => 'date',
        'periode_fin' => 'date',
        'paye_le' => 'datetime',
        'montant_encaisse' => 'decimal:2',
        'montant_commission' => 'decimal:2',
        'montant_deductions' => 'decimal:2',
        'montant_net' => 'decimal:2',
    ];

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'proprietaire_id');
    }

    public function mandat(): BelongsTo
    {
        return $this->belongsTo(Mandat::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(ReversementLigne::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }
}
