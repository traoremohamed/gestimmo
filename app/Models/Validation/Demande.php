<?php

namespace App\Models\Validation;

use App\Models\Concerns\AppartientAAgence;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Demande de validation : action en attente d'approbation, exécutée après la dernière étape.
 */
class Demande extends Model
{
    use AppartientAAgence;

    protected $table = 'wf_demandes';

    public const STATUTS = ['EN_ATTENTE' => ['En attente', 'warning'], 'REJETEE' => ['Rejetée', 'danger'], 'ANNULEE' => ['Retirée', 'secondary'],
        'EXECUTEE' => ['Approuvée et exécutée', 'success'], 'ECHEC' => ['Approuvée — exécution en échec', 'danger']];

    protected $fillable = ['agence_id', 'numero', 'circuit_id', 'circuit_version', 'action', 'filtre', 'objet_type', 'objet_id', 'reference', 'resume', 'montant',
        'donnees', 'motif', 'statut', 'etape_courante', 'echeance_etape_at', 'demandeur_id', 'demande_at', 'decide_at', 'execute_at', 'erreur', 'cle_unicite'];

    protected $casts = ['donnees' => 'array', 'montant' => 'decimal:2', 'demande_at' => 'datetime', 'decide_at' => 'datetime', 'execute_at' => 'datetime',
        'echeance_etape_at' => 'datetime'];

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }

    public function objet(): MorphTo
    {
        return $this->morphTo();
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class)->orderBy('ordre');
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
