<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contrat de travail : conditions de rémunération (un contrat actif à la fois par employé).
 */
class Contrat extends Model
{
    use AppartientAAgence;

    protected $table = 'rh_contrats';

    public const TYPES = ['CDI' => 'CDI', 'CDD' => 'CDD', 'STAGE' => 'Stage', 'JOURNALIER' => 'Journalier', 'PRESTATAIRE' => 'Prestataire'];

    public const MODES = ['FIXE' => 'Salaire mensuel fixe', 'JOURNALIER' => 'Taux journalier', 'COMMISSION' => 'Fixe + commission sur ventes'];

    protected $fillable = [
        'agence_id', 'employe_id', 'type_contrat', 'categorie', 'date_debut', 'date_fin', 'mode_remuneration', 'salaire_base', 'sursalaire',
        'taux_journalier', 'taux_commission', 'heures_hebdo', 'jours_par_mois', 'soumis_cotisations', 'soumis_impot', 'rubriques_fixes',
        'mode_paiement', 'statut',
    ];

    protected $attributes = ['statut' => 'actif', 'mode_remuneration' => 'FIXE', 'type_contrat' => 'CDI'];

    protected $casts = [
        'date_debut' => 'date', 'date_fin' => 'date', 'salaire_base' => 'decimal:2', 'sursalaire' => 'decimal:2', 'taux_journalier' => 'decimal:2',
        'taux_commission' => 'decimal:3', 'soumis_cotisations' => 'boolean', 'soumis_impot' => 'boolean', 'rubriques_fixes' => 'array',
    ];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }
}
