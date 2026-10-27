<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mandat de gestion : le propriétaire confie ses biens à l'agence contre une commission.
 */
class Mandat extends Model
{
    use AppartientAAgence, SoftDeletes;

    public const STATUTS = ['actif' => 'Actif', 'suspendu' => 'Suspendu', 'resilie' => 'Résilié'];

    protected $fillable = [
        'agence_id', 'numero', 'proprietaire_id', 'date_debut', 'date_fin', 'taux_commission', 'commission_sur_charges',
        'jour_reversement', 'statut', 'conditions',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'taux_commission' => 'decimal:2',
        'commission_sur_charges' => 'boolean',
    ];

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'proprietaire_id');
    }

    public function biens(): HasMany
    {
        return $this->hasMany(Bien::class);
    }

    public function baux(): HasMany
    {
        return $this->hasMany(Bail::class);
    }

    public function reversements(): HasMany
    {
        return $this->hasMany(Reversement::class);
    }
}
