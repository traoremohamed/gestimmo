<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bulletin extends Model
{
    use AppartientAAgence;

    protected $table = 'rh_bulletins';

    public const STATUTS = ['calcule' => ['Calculé', 'info'], 'valide' => ['Validé', 'primary'], 'partiel' => ['Payé en partie', 'warning'],
        'paye' => ['Payé', 'success'], 'annule' => ['Annulé', 'secondary']];

    protected $fillable = ['agence_id', 'numero', 'periode_id', 'employe_id', 'contrat_id', 'jours_travailles', 'lignes', 'totaux', 'brut', 'brut_imposable',
        'cotisations_salariales', 'cotisations_patronales', 'impots', 'retenues', 'net_a_payer', 'montant_paye', 'cout_employeur', 'statut'];

    protected $attributes = ['statut' => 'calcule', 'montant_paye' => 0];

    protected $casts = ['lignes' => 'array', 'totaux' => 'array', 'brut' => 'decimal:2', 'net_a_payer' => 'decimal:2', 'montant_paye' => 'decimal:2',
        'cout_employeur' => 'decimal:2'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodePaie::class, 'periode_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementSalaire::class);
    }

    public function resteAPayer(): float
    {
        return max(0, round((float) $this->net_a_payer - (float) $this->montant_paye, 2));
    }
}
