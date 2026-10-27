<?php

namespace App\Models;

use App\Enums\StatutIntervention;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Intervention extends Model
{
    use AppartientAAgence;

    public const A_CHARGE_DE = ['proprietaire' => 'Propriétaire', 'locataire' => 'Locataire', 'copropriete' => 'Copropriété', 'agence' => 'Agence'];

    protected $fillable = [
        'agence_id', 'numero', 'bien_id', 'copropriete_id', 'reclamation_id', 'fournisseur_id', 'objet', 'description', 'priorite', 'statut',
        'date_signalement', 'date_prevue', 'date_realisation', 'cout_estime', 'cout_reel', 'a_charge_de', 'depense_id', 'created_by',
    ];

    protected $attributes = ['statut' => 'signalee', 'priorite' => 'normale', 'a_charge_de' => 'proprietaire'];

    protected $casts = [
        'statut' => StatutIntervention::class,
        'date_signalement' => 'date',
        'date_prevue' => 'date',
        'date_realisation' => 'date',
        'cout_estime' => 'decimal:2',
        'cout_reel' => 'decimal:2',
    ];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function copropriete(): BelongsTo
    {
        return $this->belongsTo(Copropriete::class);
    }

    public function reclamation(): BelongsTo
    {
        return $this->belongsTo(Reclamation::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'fournisseur_id');
    }

    public function depense(): BelongsTo
    {
        return $this->belongsTo(Depense::class);
    }
}
