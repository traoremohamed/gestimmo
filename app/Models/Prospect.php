<?php

namespace App\Models;

use App\Enums\StatutProspect;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projet d'un prospect (achat, location, mise en gestion, syndic, meublé).
 */
class Prospect extends Model
{
    use AppartientAAgence;

    public const PROJETS = ['achat' => 'Achat', 'location' => 'Location', 'gestion' => 'Mise en gestion', 'syndic' => 'Syndic', 'meuble' => 'Séjour meublé'];

    protected $fillable = [
        'agence_id', 'tiers_id', 'commercial_id', 'projet', 'type_bien', 'programme_id', 'budget_min', 'budget_max', 'superficie_min',
        'localisation', 'canal', 'statut', 'date_relance', 'commentaire', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'nouveau', 'projet' => 'achat'];

    protected $casts = [
        'statut' => StatutProspect::class,
        'date_relance' => 'date',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
    ];

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }
}
