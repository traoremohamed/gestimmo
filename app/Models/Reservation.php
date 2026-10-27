<?php

namespace App\Models;

use App\Enums\StatutReservation;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réservation (intention d'achat) : bloque un bien jusqu'à sa date d'expiration.
 */
class Reservation extends Model
{
    use AppartientAAgence;

    protected $fillable = [
        'agence_id', 'numero', 'tiers_id', 'bien_id', 'user_id', 'date_reservation', 'date_expiration', 'statut', 'commentaire',
        'motif_liberation', 'vente_id', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'active'];

    protected $casts = ['statut' => StatutReservation::class, 'date_reservation' => 'date', 'date_expiration' => 'date'];

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function estActive(): bool
    {
        return $this->statut === StatutReservation::Active;
    }
}
