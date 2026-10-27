<?php

namespace App\Models;

use App\Enums\NatureLoyer;
use App\Enums\StatutBail;
use App\Models\Concerns\AppartientAAgence;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bail : contrat de location d'un bien à un locataire (ancien « dossier » de location).
 */
class Bail extends Model
{
    use AppartientAAgence, SoftDeletes;

    protected $table = 'baux';

    public const TYPES = ['habitation' => 'Habitation', 'professionnel' => 'Professionnel', 'commercial' => 'Commercial'];

    protected $fillable = [
        'agence_id', 'numero', 'bien_id', 'locataire_id', 'garant_id', 'mandat_id', 'type_bail', 'statut', 'date_debut', 'duree_mois',
        'date_fin', 'tacite_reconduction', 'loyer_mensuel', 'charges_mensuelles', 'periodicite_mois', 'jour_echeance', 'mois_caution',
        'mois_avance', 'frais_agence', 'depot_garantie', 'caution_sodeci', 'caution_cie', 'compteur_sodeci_numero', 'compteur_cie_numero',
        'compteur_cie_credit', 'compteur_cie_montant_credit', 'taux_penalite', 'taux_revision', 'date_prochaine_revision',
        'loyers_generes_jusqu_au', 'date_resiliation', 'motif_resiliation', 'depot_restitue', 'observation', 'created_by', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'brouillon'];

    protected $casts = [
        'statut' => StatutBail::class,
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_prochaine_revision' => 'date',
        'loyers_generes_jusqu_au' => 'date',
        'date_resiliation' => 'date',
        'tacite_reconduction' => 'boolean',
        'compteur_cie_credit' => 'boolean',
        'caution_sodeci' => 'decimal:2',
        'caution_cie' => 'decimal:2',
        'compteur_cie_montant_credit' => 'decimal:2',
        'loyer_mensuel' => 'decimal:2',
        'charges_mensuelles' => 'decimal:2',
        'frais_agence' => 'decimal:2',
        'depot_garantie' => 'decimal:2',
        'depot_restitue' => 'decimal:2',
    ];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function locataire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'locataire_id');
    }

    public function garant(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'garant_id');
    }

    public function mandat(): BelongsTo
    {
        return $this->belongsTo(Mandat::class);
    }

    public function loyers(): HasMany
    {
        return $this->hasMany(Loyer::class);
    }

    public function etatsLieux(): HasMany
    {
        return $this->hasMany(EtatLieux::class);
    }

    public function decomptes(): HasMany
    {
        return $this->hasMany(DecompteSortie::class);
    }

    public function montantPeriode(): string
    {
        return Montant::mul(Montant::add($this->loyer_mensuel, $this->charges_mensuelles), $this->periodicite_mois ?: 1);
    }

    public function soldeDu(): string
    {
        return Montant::sub($this->loyers()->impayes()->sum('montant'), $this->loyers()->impayes()->sum('montant_paye'));
    }

    public function nombreLoyersImpayes(): int
    {
        return $this->loyers()->where('nature', NatureLoyer::Loyer->value)->enRetard()->count();
    }
}
