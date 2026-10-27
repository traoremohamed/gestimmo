<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Rendez-vous.
 */
enum StatutRendezVous: string
{
    use AvecLibelle;

    case Planifie = 'planifie';
    case Reporte = 'reporte';
    case Realise = 'realise';
    case Annule = 'annule';

    public const LIBELLES = [
        'planifie' => 'Planifié',
        'reporte' => 'Reporté',
        'realise' => 'Réalisé',
        'annule' => 'Annulé',
    ];

    public const COULEURS = [
        'planifie' => 'primary',
        'reporte' => 'warning',
        'realise' => 'success',
        'annule' => 'secondary',
    ];
}
