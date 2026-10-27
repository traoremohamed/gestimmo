<?php

namespace App\Models;

use App\Enums\StatutAppelFonds;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Appel de fonds : montant réparti entre les lots selon une clé de tantièmes.
 */
class AppelFonds extends Model
{
    use AppartientAAgence;

    protected $table = 'appels_fonds';

    public const TYPES = ['provision' => 'Provision sur budget', 'travaux' => 'Travaux', 'fonds_travaux' => 'Fonds de travaux', 'exceptionnel' => 'Exceptionnel'];

    protected $fillable = [
        'agence_id', 'copropriete_id', 'budget_id', 'numero', 'type', 'libelle', 'cle_repartition_id', 'date_appel', 'date_echeance',
        'montant_total', 'statut', 'created_by',
    ];

    protected $attributes = ['statut' => 'brouillon'];

    protected $casts = [
        'statut' => StatutAppelFonds::class,
        'date_appel' => 'date',
        'date_echeance' => 'date',
        'montant_total' => 'decimal:2',
    ];

    public function copropriete(): BelongsTo
    {
        return $this->belongsTo(Copropriete::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function cle(): BelongsTo
    {
        return $this->belongsTo(CleRepartition::class, 'cle_repartition_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(AppelLigne::class, 'appel_fonds_id');
    }
}
