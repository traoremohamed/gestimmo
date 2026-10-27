<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariablePaie extends Model
{
    protected $table = 'rh_variables_paie';

    protected $fillable = ['employe_id', 'periode_id', 'rubrique_code', 'quantite', 'montant', 'libelle'];

    protected $casts = ['montant' => 'decimal:2'];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }
}
