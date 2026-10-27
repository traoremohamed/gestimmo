<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Intervention / travaux.
 */
enum StatutIntervention: string
{
    use AvecLibelle;

    case Signalee = 'signalee';
    case Planifiee = 'planifiee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Annulee = 'annulee';

    public const LIBELLES = [
        'signalee' => 'Signalée',
        'planifiee' => 'Planifiée',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
    ];

    public const COULEURS = [
        'signalee' => 'danger',
        'planifiee' => 'info',
        'en_cours' => 'warning',
        'terminee' => 'success',
        'annulee' => 'secondary',
    ];
}
