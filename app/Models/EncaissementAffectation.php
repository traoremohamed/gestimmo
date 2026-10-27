<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EncaissementAffectation extends Model
{
    protected $fillable = ['encaissement_id', 'affectable_type', 'affectable_id', 'montant'];

    protected $casts = ['montant' => 'decimal:2'];

    public function encaissement(): BelongsTo
    {
        return $this->belongsTo(Encaissement::class);
    }

    public function affectable(): MorphTo
    {
        return $this->morphTo();
    }
}
