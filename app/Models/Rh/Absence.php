<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Congé ou absence : demandé, puis validé ou refusé par un responsable.
 */
class Absence extends Model
{
    use AppartientAAgence;

    protected $table = 'rh_absences';

    public const STATUTS = ['demandee' => ['Demandée', 'warning'], 'validee' => ['Validée', 'success'], 'refusee' => ['Refusée', 'danger'], 'annulee' => ['Annulée', 'secondary']];

    /** types non rémunérés (déduits du salaire) */
    public const NON_REMUNEREES = ['SANS_SOLDE', 'ABSENCE_INJUSTIFIEE'];

    /** types décomptés du solde de congés */
    public const DECOMPTEES = ['CONGE_ANNUEL'];

    protected $fillable = ['agence_id', 'employe_id', 'type', 'date_debut', 'date_fin', 'nombre_jours', 'motif', 'statut', 'decide_par', 'decide_le',
        'commentaire_decision', 'created_by'];

    protected $attributes = ['statut' => 'demandee'];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'decide_le' => 'datetime', 'nombre_jours' => 'decimal:2'];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par');
    }
}
