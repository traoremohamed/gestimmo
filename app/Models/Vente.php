<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use App\Enums\StatutVente;
use App\Models\Concerns\AppartientAAgence;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contrat de vente (ancien « dossier » de vente).
 */
class Vente extends Model
{
    use AppartientAAgence, SoftDeletes;

    public const MODES_FINANCEMENT = ['comptant' => 'Comptant', 'echelonne' => 'Échelonné', 'credit' => 'Crédit bancaire'];

    protected $fillable = [
        'agence_id', 'numero', 'acquereur_id', 'commercial_id', 'statut', 'date_contrat', 'prix_total', 'remise', 'montant_net', 'montant_frais',
        'mode_financement', 'taux_acompte', 'montant_acompte', 'nombre_echeances', 'periodicite_mois', 'date_premiere_echeance',
        'taux_commission', 'montant_commission', 'observation', 'valide_par', 'valide_le', 'date_desistement', 'motif_desistement',
        'observation_desistement', 'montant_retenu', 'montant_a_rembourser', 'rembourse_le', 'depense_remboursement_id', 'vente_origine_id',
        'created_by', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'brouillon'];

    protected $casts = [
        'statut' => StatutVente::class,
        'date_contrat' => 'date',
        'date_premiere_echeance' => 'date',
        'date_desistement' => 'date',
        'valide_le' => 'datetime',
        'rembourse_le' => 'datetime',
        'prix_total' => 'decimal:2',
        'remise' => 'decimal:2',
        'montant_net' => 'decimal:2',
        'montant_frais' => 'decimal:2',
        'montant_acompte' => 'decimal:2',
        'montant_commission' => 'decimal:2',
        'montant_retenu' => 'decimal:2',
        'montant_a_rembourser' => 'decimal:2',
    ];

    public function acquereur(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'acquereur_id');
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(VenteBien::class);
    }

    public function biens(): BelongsToMany
    {
        return $this->belongsToMany(Bien::class, 'vente_biens')->withPivot('prix')->withTimestamps();
    }

    public function frais(): HasMany
    {
        return $this->hasMany(VenteFrais::class);
    }

    public function echeances(): HasMany
    {
        return $this->hasMany(Echeance::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function origine(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_origine_id');
    }

    public function estModifiable(): bool
    {
        return $this->statut === StatutVente::Brouillon;
    }

    /**
     * Total encaissé sur le prix (échéances, acompte compris), hors frais annexes.
     */
    public function totalPayePrix(): string
    {
        return Montant::normaliser($this->echeances()->sum('montant_paye'));
    }

    public function totalPayeFrais(): string
    {
        return Montant::normaliser($this->frais()->sum('montant_paye'));
    }

    public function totalPaye(): string
    {
        return Montant::add($this->totalPayePrix(), $this->totalPayeFrais());
    }

    public function resteAPayer(): string
    {
        $du = Montant::add(
            $this->echeances()->where('statut', '!=', StatutPaiement::Annule->value)->sum('montant'),
            $this->frais()->where('statut', '!=', StatutPaiement::Annule->value)->sum('montant')
        );

        return Montant::sub($du, $this->totalPaye());
    }

    public function tauxRecouvrement(): float
    {
        $du = (float) $this->montant_net + (float) $this->montant_frais;

        return $du > 0 ? round(((float) $this->totalPaye()) / $du * 100, 1) : 0.0;
    }
}
