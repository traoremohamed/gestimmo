<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenteBien extends Model
{
    protected $fillable = ['vente_id', 'bien_id', 'prix'];

    protected $casts = ['prix' => 'decimal:2'];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }
}
