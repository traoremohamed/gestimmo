<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReversementLigne extends Model
{
    protected $fillable = ['reversement_id', 'loyer_id', 'libelle', 'montant_encaisse', 'taux_commission', 'montant_commission'];

    protected $casts = ['montant_encaisse' => 'decimal:2', 'montant_commission' => 'decimal:2'];

    public function reversement(): BelongsTo
    {
        return $this->belongsTo(Reversement::class);
    }

    public function loyer(): BelongsTo
    {
        return $this->belongsTo(Loyer::class);
    }
}
