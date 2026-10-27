<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use AppartientAAgence;

    protected $table = 'rh_presences';

    public const STATUTS = ['present' => ['Présent', 'success'], 'retard' => ['Retard', 'warning'], 'demi_journee' => ['Demi-journée', 'info'],
        'absent' => ['Absent', 'danger'], 'conge' => ['Congé', 'primary'], 'repos' => ['Repos', 'secondary']];

    protected $fillable = ['agence_id', 'employe_id', 'date', 'statut', 'heure_arrivee', 'heure_depart', 'heures_supplementaires', 'commentaire'];

    protected $casts = ['date' => 'date', 'heures_supplementaires' => 'decimal:2'];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }
}
