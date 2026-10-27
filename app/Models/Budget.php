<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Budget prévisionnel annuel d'une copropriété.
 */
class Budget extends Model
{
    public const STATUTS = ['brouillon' => 'Brouillon', 'vote' => 'Voté', 'cloture' => 'Clôturé'];

    protected $fillable = ['copropriete_id', 'exercice', 'date_debut', 'date_fin', 'statut', 'montant_total', 'vote_le'];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'vote_le' => 'date', 'montant_total' => 'decimal:2'];

    public function copropriete(): BelongsTo
    {
        return $this->belongsTo(Copropriete::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(BudgetLigne::class);
    }

    public function appels(): HasMany
    {
        return $this->hasMany(AppelFonds::class);
    }
}
