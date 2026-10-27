<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lot de copropriété et ses tantièmes de charges générales.
 */
class CoproLot extends Model
{
    protected $fillable = ['copropriete_id', 'bien_id', 'numero', 'designation', 'coproprietaire_id', 'occupant_id', 'tantiemes'];

    protected $casts = ['tantiemes' => 'decimal:4'];

    public function copropriete(): BelongsTo
    {
        return $this->belongsTo(Copropriete::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function coproprietaire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'coproprietaire_id');
    }

    public function occupant(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'occupant_id');
    }

    public function appelLignes(): HasMany
    {
        return $this->hasMany(AppelLigne::class);
    }
}
