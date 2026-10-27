<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;

/**
 * Rubrique de paie paramétrable (gain, cotisation, impôt, retenue).
 */
class Rubrique extends Model
{
    use AppartientAAgence;

    protected $table = 'rh_rubriques';

    public const TYPES = ['GAIN' => 'Gain', 'COTISATION_SALARIALE' => 'Cotisation salariale', 'COTISATION_PATRONALE' => 'Cotisation patronale',
        'IMPOT' => 'Impôt', 'RETENUE' => 'Retenue'];

    public const MODES = ['SALAIRE_BASE' => 'Salaire de base', 'JOURS' => 'Jours travaillés × taux', 'COMMISSION' => '% du CA des ventes',
        'FIXE' => 'Montant fixe', 'SAISIE' => 'Saisie sur la période', 'ANCIENNETE' => 'Ancienneté (% par an)', 'POURCENTAGE' => 'Pourcentage d\'une base',
        'BAREME' => 'Barème progressif', 'AVANCE' => 'Remboursement des avances'];

    public const BASES = ['BASE' => 'Salaire de base', 'BRUT' => 'Brut', 'BRUT_IMPOSABLE' => 'Brut imposable', 'BRUT_COTISABLE' => 'Brut cotisable', 'CA_VENDEUR' => 'CA du commercial'];

    protected $fillable = ['agence_id', 'code', 'numero', 'libelle', 'type', 'mode_calcul', 'base', 'taux', 'montant', 'plafond', 'franchise', 'bareme',
        'reduction_parts', 'anciennete_min_ans', 'imposable', 'cotisable', 'proratiser', 'groupe', 'compte_charge', 'compte_tiers', 'ordre', 'actif'];

    protected $casts = ['bareme' => 'array', 'reduction_parts' => 'array', 'imposable' => 'boolean', 'cotisable' => 'boolean',
        'proratiser' => 'boolean', 'actif' => 'boolean'];
}
