<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromessePaiement extends Model
{
    use AppartientAAgence;

    protected $table = 'promesses_paiement';

    public const STATUTS = ['en_attente' => ['En attente', 'warning'], 'tenue' => ['Tenue', 'success'], 'non_tenue' => ['Non tenue', 'danger'], 'annulee' => ['Annulée', 'secondary']];

    protected $fillable = ['agence_id', 'recouvrement_id', 'date_promesse', 'montant', 'statut', 'commentaire', 'user_id'];

    protected $attributes = ['statut' => 'en_attente'];

    protected $casts = ['date_promesse' => 'date', 'montant' => 'decimal:2'];

    public function recouvrement(): BelongsTo
    {
        return $this->belongsTo(Recouvrement::class);
    }
}
