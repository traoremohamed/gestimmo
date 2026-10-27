<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Objectif de chiffre d'affaires d'un commercial (ancien chrifre_affaire_commerciale).
 */
class Objectif extends Model
{
    use AppartientAAgence;

    protected $fillable = ['agence_id', 'user_id', 'activite', 'periode_debut', 'periode_fin', 'montant_objectif', 'commentaire'];

    protected $casts = ['periode_debut' => 'date', 'periode_fin' => 'date', 'montant_objectif' => 'decimal:2'];

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
