<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Copropriété gérée par l'agence en tant que syndic.
 */
class Copropriete extends Model
{
    use AppartientAAgence, SoftDeletes;

    protected $fillable = [
        'agence_id', 'nom', 'immeuble_id', 'programme_id', 'adresse', 'immatriculation', 'mandat_debut', 'mandat_fin', 'honoraires_annuels',
        'total_tantiemes', 'taux_fonds_travaux', 'exercice_debut_mois', 'banque', 'rib', 'statut', 'reglement',
    ];

    protected $casts = [
        'mandat_debut' => 'date',
        'mandat_fin' => 'date',
        'honoraires_annuels' => 'decimal:2',
        'taux_fonds_travaux' => 'decimal:2',
    ];

    public function immeuble(): BelongsTo
    {
        return $this->belongsTo(Immeuble::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(CoproLot::class);
    }

    public function cles(): HasMany
    {
        return $this->hasMany(CleRepartition::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function appels(): HasMany
    {
        return $this->hasMany(AppelFonds::class);
    }

    public function assemblees(): HasMany
    {
        return $this->hasMany(Assemblee::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    public function totalTantiemesLots(): float
    {
        return (float) $this->lots()->sum('tantiemes');
    }

    /**
     * Les tantièmes des lots doivent couvrir exactement le total déclaré.
     */
    public function tantiemesEquilibres(): bool
    {
        return abs($this->totalTantiemesLots() - (float) $this->total_tantiemes) < 0.0001;
    }
}
