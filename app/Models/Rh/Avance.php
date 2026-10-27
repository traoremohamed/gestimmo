<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avance sur salaire ou prêt, remboursé par retenue sur les bulletins suivants.
 */
class Avance extends Model
{
    use AppartientAAgence;

    protected $table = 'rh_avances';

    protected $fillable = ['agence_id', 'employe_id', 'numero', 'type', 'date_octroi', 'montant', 'nombre_echeances', 'montant_echeance',
        'montant_rembourse', 'mode_paiement', 'statut', 'motif', 'created_by'];

    protected $attributes = ['statut' => 'en_cours', 'montant_rembourse' => 0];

    protected $casts = ['date_octroi' => 'date', 'montant' => 'decimal:2', 'montant_echeance' => 'decimal:2', 'montant_rembourse' => 'decimal:2'];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function reste(): float
    {
        return max(0, (float) $this->montant - (float) $this->montant_rembourse);
    }
}
