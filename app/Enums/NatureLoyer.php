<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Nature d'un avis d'échéance locatif.
 */
enum NatureLoyer: string
{
    use AvecLibelle;

    case Loyer = 'loyer';
    case DepotGarantie = 'depot_garantie';
    case Avance = 'avance';
    case FraisAgence = 'frais_agence';
    case Penalite = 'penalite';
    case Regularisation = 'regularisation';
    case CautionSodeci = 'caution_sodeci';
    case CautionCie = 'caution_cie';
    case Retenue = 'retenue';

    public const LIBELLES = [
        'loyer' => 'Loyer',
        'depot_garantie' => 'Dépôt de garantie',
        'avance' => 'Avance sur loyer',
        'frais_agence' => 'Frais d\'agence',
        'penalite' => 'Pénalité de retard',
        'regularisation' => 'Régularisation',
        'caution_sodeci' => 'Caution compteur SODECI',
        'caution_cie' => 'Caution compteur CIE',
        'retenue' => 'Retenue de sortie',
    ];

    public const COULEURS = [
        'loyer' => 'primary',
        'depot_garantie' => 'warning',
        'avance' => 'info',
        'frais_agence' => 'dark',
        'penalite' => 'danger',
        'regularisation' => 'secondary',
        'caution_sodeci' => 'warning',
        'caution_cie' => 'warning',
        'retenue' => 'danger',
    ];
}
