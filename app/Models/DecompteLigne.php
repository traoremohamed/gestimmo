<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Ligne d'un décompte de sortie ; les justificatifs (reçus de rechargement CIE, dernières factures…) sont des documents GED rattachés.
 */
class DecompteLigne extends Model
{
    use AppartientAAgence;

    protected $table = 'decompte_lignes';

    protected $fillable = ['agence_id', 'decompte_id', 'type', 'sens', 'libelle', 'montant', 'loyer_id', 'ordre'];

    protected $casts = ['montant' => 'decimal:2'];

    public function decompte(): BelongsTo
    {
        return $this->belongsTo(DecompteSortie::class, 'decompte_id');
    }

    public function loyer(): BelongsTo
    {
        return $this->belongsTo(Loyer::class);
    }

    public function justificatifs(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function exigeJustificatif(): bool
    {
        return (bool) (DecompteSortie::TYPES[$this->type][2] ?? false);
    }
}
