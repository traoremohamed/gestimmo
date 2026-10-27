<?php

namespace App\Models\Compta;

use App\Models\Concerns\AppartientAAgence;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Pièce comptable équilibrée. Jamais supprimée : une annulation produit une contre-passation.
 */
class Ecriture extends Model
{
    use AppartientAAgence;

    protected $table = 'cpt_ecritures';

    public const NATURES = ['normale' => 'Automatique', 'manuelle' => 'Saisie manuelle', 'contrepassation' => 'Contre-passation',
        'a_nouveau' => 'À-nouveaux', 'cloture' => 'Clôture'];

    protected $fillable = ['agence_id', 'exercice_id', 'journal_id', 'numero', 'date_ecriture', 'libelle', 'piece', 'operation', 'source_type', 'source_id',
        'nature', 'ecriture_origine_id', 'total', 'created_by'];

    protected $casts = ['date_ecriture' => 'date', 'total' => 'decimal:2'];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(Ligne::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function origine(): BelongsTo
    {
        return $this->belongsTo(Ecriture::class, 'ecriture_origine_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
