<?php

namespace App\Models\Validation;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Décision d'une étape (historique non modifiable).
 */
class Decision extends Model
{
    protected $table = 'wf_decisions';

    public $timestamps = false;

    protected $fillable = ['demande_id', 'etape_id', 'ordre', 'etape_libelle', 'decision', 'commentaire', 'decide_par', 'decide_at'];

    protected $casts = ['decide_at' => 'datetime'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par');
    }
}
