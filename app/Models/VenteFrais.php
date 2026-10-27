<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use App\Models\Concerns\AppartientAAgence;
use App\Models\Concerns\EstPayable;
use App\Models\Concerns\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Frais annexe d'un contrat de vente, avec éventuel document à remettre au client.
 */
class VenteFrais extends Model implements Payable
{
    use AppartientAAgence, EstPayable;

    public const STATUTS_DOCUMENT = ['attendu' => 'Attendu', 'disponible' => 'Disponible', 'notifie' => 'Client notifié', 'remis' => 'Remis'];

    protected $table = 'vente_frais';

    protected $fillable = [
        'agence_id', 'vente_id', 'frais_type_id', 'libelle', 'montant', 'montant_paye', 'statut', 'document_requis', 'document',
        'statut_document', 'document_notifie_le',
    ];

    protected $attributes = ['statut' => 'a_payer', 'montant_paye' => 0];

    protected $casts = [
        'statut' => StatutPaiement::class,
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'document_requis' => 'boolean',
        'document_notifie_le' => 'datetime',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function fraisType(): BelongsTo
    {
        return $this->belongsTo(FraisType::class);
    }

    public function libelleAffectation(): string
    {
        return $this->libelle . ' — ' . $this->vente?->numero;
    }

    public function activite(): string
    {
        return 'vente';
    }

    protected function colonneDateEcheance(): string
    {
        return 'created_at';
    }
}
