<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampagneDestinataire extends Model
{
    protected $fillable = ['campagne_id', 'tiers_id', 'nom', 'email', 'telephone', 'statut', 'envoye_le'];

    protected $casts = ['envoye_le' => 'datetime'];

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }
}
