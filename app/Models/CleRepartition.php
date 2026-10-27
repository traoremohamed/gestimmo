<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Clé de répartition spéciale (ascenseur, bâtiment B…) ; les charges générales
 * utilisent directement les tantièmes des lots.
 */
class CleRepartition extends Model
{
    protected $table = 'cles_repartition';

    protected $fillable = ['copropriete_id', 'code', 'libelle'];

    public function copropriete(): BelongsTo
    {
        return $this->belongsTo(Copropriete::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(CleLot::class);
    }
}
