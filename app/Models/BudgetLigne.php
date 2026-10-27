<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLigne extends Model
{
    protected $fillable = ['budget_id', 'poste', 'libelle', 'cle_repartition_id', 'montant'];

    protected $casts = ['montant' => 'decimal:2'];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function cle(): BelongsTo
    {
        return $this->belongsTo(CleRepartition::class, 'cle_repartition_id');
    }
}
