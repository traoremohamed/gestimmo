<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résolution soumise au vote en assemblée générale (voix exprimées en tantièmes).
 */
class Resolution extends Model
{
    public const MAJORITES = [
        'simple' => 'Majorité simple (des voix exprimées)',
        'absolue' => 'Majorité absolue (de tous les copropriétaires)',
        'double' => 'Double majorité (2/3 de tous les copropriétaires)',
        'unanimite' => 'Unanimité',
    ];

    protected $fillable = ['assemblee_id', 'numero', 'intitule', 'description', 'majorite', 'voix_pour', 'voix_contre', 'voix_abstention', 'adoptee'];

    protected $casts = ['voix_pour' => 'decimal:4', 'voix_contre' => 'decimal:4', 'voix_abstention' => 'decimal:4', 'adoptee' => 'boolean'];

    public function assemblee(): BelongsTo
    {
        return $this->belongsTo(Assemblee::class);
    }
}
