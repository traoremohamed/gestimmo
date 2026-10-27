<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce d'identité / justificatif d'un tiers (fichier sur le disque privé).
 */
class TiersPiece extends Model
{
    use AppartientAAgence;

    protected $fillable = ['agence_id', 'tiers_id', 'type_piece', 'numero', 'date_delivrance', 'date_expiration', 'lieu_delivrance', 'fichier'];

    protected $casts = ['date_delivrance' => 'date', 'date_expiration' => 'date'];

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }

    public function estExpiree(): bool
    {
        return $this->date_expiration !== null && $this->date_expiration->isPast();
    }
}
