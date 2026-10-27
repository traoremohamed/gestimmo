<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relance extends Model
{
    use AppartientAAgence;

    protected $fillable = ['agence_id', 'recouvrement_id', 'niveau', 'canal', 'montant_du', 'objet', 'message', 'statut', 'automatique', 'user_id'];

    protected $casts = ['montant_du' => 'decimal:2', 'automatique' => 'boolean'];

    public function recouvrement(): BelongsTo
    {
        return $this->belongsTo(Recouvrement::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
