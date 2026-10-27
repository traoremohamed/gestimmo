<?php

namespace App\Models\Validation;

use App\Models\Concerns\AppartientAAgence;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Circuit de validation d'une action (wf_circuits) : BROUILLON → APPROUVE → ACTIF → RETIRE, versionné.
 * Un circuit actif n'est plus modifiable : on en crée une nouvelle version.
 */
class Circuit extends Model
{
    use AppartientAAgence;

    protected $table = 'wf_circuits';

    public const STATUTS = ['BROUILLON' => ['Brouillon', 'secondary'], 'APPROUVE' => ['Approuvé', 'info'], 'ACTIF' => ['Actif', 'success'], 'RETIRE' => ['Retiré', 'dark']];

    protected $fillable = ['agence_id', 'circuit_precedent_id', 'action', 'filtre', 'montant_declenchement', 'libelle', 'description', 'version', 'statut',
        'approuve_par', 'approuve_at', 'active_at', 'created_by'];

    protected $attributes = ['statut' => 'BROUILLON', 'version' => 1];

    protected $casts = ['montant_declenchement' => 'decimal:2', 'approuve_at' => 'datetime', 'active_at' => 'datetime'];

    public function etapes(): HasMany
    {
        return $this->hasMany(Etape::class, 'circuit_id')->orderBy('ordre');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }

    public function estModifiable(): bool
    {
        return $this->statut === 'BROUILLON';
    }
}
