<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dépense / décaissement : charges de copropriété, travaux, remboursement d'un client…
 */
class Depense extends Model
{
    use AppartientAAgence;

    public const STATUTS = ['a_payer' => 'À payer', 'payee' => 'Payée', 'annulee' => 'Annulée'];

    protected $fillable = [
        'agence_id', 'numero', 'date_depense', 'categorie', 'libelle', 'montant', 'fournisseur_id', 'beneficiaire_id', 'copropriete_id',
        'bien_id', 'refacturable_proprietaire', 'reversement_id', 'mode_paiement', 'reference', 'justificatif', 'statut', 'created_by',
    ];

    protected $attributes = ['statut' => 'payee'];

    protected $casts = ['date_depense' => 'date', 'montant' => 'decimal:2', 'refacturable_proprietaire' => 'boolean'];

    /**
     * Comptabilisation automatique : à la mise en paiement ; une modification ou une annulation
     * contre-passe l'écriture précédente (jamais de suppression).
     */
    protected static function booted(): void
    {
        static::saved(function (Depense $depense) {
            $compta = app(\App\Services\Compta\ComptaService::class);
            $changement = $depense->wasChanged(['montant', 'categorie', 'mode_paiement', 'copropriete_id', 'refacturable_proprietaire', 'statut', 'date_depense']);

            if ($depense->wasRecentlyCreated || $changement) {
                $compta->tenter(function () use ($compta, $depense) {
                    $compta->annulerPiece($depense, 'Modification de la dépense');
                    \App\Models\Compta\Ecriture::where('source_type', $depense->getMorphClass())->where('source_id', $depense->id)->where('nature', 'normale')
                        ->update(['nature' => 'remplacee']);

                    return $compta->depense($depense);
                });
            }
        });
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'fournisseur_id');
    }

    public function beneficiaire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'beneficiaire_id');
    }

    public function copropriete(): BelongsTo
    {
        return $this->belongsTo(Copropriete::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function reversement(): BelongsTo
    {
        return $this->belongsTo(Reversement::class);
    }
}
