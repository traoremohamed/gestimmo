<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleLot extends Model
{
    protected $fillable = ['cle_repartition_id', 'copro_lot_id', 'tantiemes'];

    protected $casts = ['tantiemes' => 'decimal:4'];

    public function cle(): BelongsTo
    {
        return $this->belongsTo(CleRepartition::class, 'cle_repartition_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(CoproLot::class, 'copro_lot_id');
    }
}
