<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Décompte de sortie du locataire : liquidation des garanties (dépôt, cautions SODECI / CIE),
 * crédits dus au locataire (crédit de compteur CIE) et retenues (réparations, factures, impayés).
 */
class DecompteSortie extends Model
{
    use AppartientAAgence;

    protected $table = 'decomptes_sortie';

    public const STATUTS = [
        'brouillon' => ['Brouillon', 'secondary'], 'valide' => ['Validé — à restituer', 'warning'], 'rembourse' => ['Garanties restituées', 'success'],
        'solde' => ['Soldé', 'success'], 'reste_du' => ['Reste dû par le locataire', 'danger'],
    ];

    /**
     * Types de ligne : [libellé, sens (credit = dû au locataire, debit = dû par le locataire), justificatif exigé]
     */
    public const TYPES = [
        'depot_garantie' => ['Dépôt de garantie versé', 'credit', false],
        'caution_sodeci' => ['Caution SODECI versée', 'credit', false],
        'caution_cie' => ['Caution CIE versée', 'credit', false],
        'credit_compteur_cie' => ['Crédit de compteur CIE remboursé par le locataire', 'credit', true],
        'autre_credit' => ['Autre somme due au locataire', 'credit', false],
        'impaye' => ['Loyers et charges impayés', 'debit', false],
        'reparation' => ['Réparations (état des lieux de sortie)', 'debit', false],
        'facture_sodeci' => ['Dernière facture SODECI non réglée', 'debit', true],
        'facture_cie' => ['Dernière facture CIE non réglée', 'debit', true],
        'autre_retenue' => ['Autre retenue', 'debit', false],
    ];

    protected $fillable = [
        'agence_id', 'numero', 'bail_id', 'tiers_id', 'etat_lieux_id', 'date_sortie', 'statut', 'total_credits', 'total_retenues', 'montant_compense',
        'montant_a_rembourser', 'reste_du', 'encaissement_id', 'depense_id', 'depense_compteur_id', 'valide_le', 'valide_par', 'rembourse_le', 'observations',
    ];

    protected $attributes = ['statut' => 'brouillon'];

    protected $casts = [
        'date_sortie' => 'date', 'valide_le' => 'datetime', 'rembourse_le' => 'date',
        'total_credits' => 'decimal:2', 'total_retenues' => 'decimal:2', 'montant_compense' => 'decimal:2', 'montant_a_rembourser' => 'decimal:2', 'reste_du' => 'decimal:2',
    ];

    public function bail(): BelongsTo
    {
        return $this->belongsTo(Bail::class);
    }

    public function locataire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'tiers_id');
    }

    public function etatLieux(): BelongsTo
    {
        return $this->belongsTo(EtatLieux::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(DecompteLigne::class, 'decompte_id')->orderBy('ordre')->orderBy('id');
    }

    public function encaissement(): BelongsTo
    {
        return $this->belongsTo(Encaissement::class);
    }

    public function depense(): BelongsTo
    {
        return $this->belongsTo(Depense::class);
    }

    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon';
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut][0] ?? $this->statut;
    }

    public function couleurStatut(): string
    {
        return self::STATUTS[$this->statut][1] ?? 'secondary';
    }
}
