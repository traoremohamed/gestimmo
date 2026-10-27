<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Encaissement (reçu numéroté), réparti sur une ou plusieurs dettes.
 * Un encaissement n'est jamais supprimé : il est annulé (contre-passation des affectations).
 */
class Encaissement extends Model
{
    use AppartientAAgence;

    public const ACTIVITES = ['vente' => 'Vente', 'location' => 'Location', 'syndic' => 'Syndic', 'meuble' => 'Meublé', 'autre' => 'Autre'];

    protected $fillable = [
        'agence_id', 'numero', 'tiers_id', 'date_encaissement', 'montant', 'mode_paiement', 'reference', 'banque', 'activite',
        'observation', 'statut', 'motif_annulation', 'annule_par', 'annule_le', 'caissier_id', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'valide'];

    protected $casts = ['date_encaissement' => 'date', 'annule_le' => 'datetime', 'montant' => 'decimal:2'];

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }

    public function caissier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caissier_id');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(EncaissementAffectation::class);
    }

    public function estAnnule(): bool
    {
        return $this->statut === 'annule';
    }

    public function scopeValides(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('statut'), 'valide');
    }
}
