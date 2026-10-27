<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementSalaire extends Model
{
    use AppartientAAgence;

    protected $table = 'rh_paiements_salaire';

    protected $fillable = ['agence_id', 'numero', 'bulletin_id', 'employe_id', 'date_paiement', 'montant', 'mode_paiement', 'reference', 'statut', 'created_by'];

    protected $attributes = ['statut' => 'valide'];

    protected $casts = ['date_paiement' => 'date', 'montant' => 'decimal:2'];

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }
}
