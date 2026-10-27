<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Pipeline commercial.
 */
enum StatutProspect: string
{
    use AvecLibelle;

    case Nouveau = 'nouveau';
    case Qualifie = 'qualifie';
    case Negociation = 'negociation';
    case Converti = 'converti';
    case Perdu = 'perdu';

    public const LIBELLES = [
        'nouveau' => 'Nouveau',
        'qualifie' => 'Qualifié',
        'negociation' => 'En négociation',
        'converti' => 'Converti',
        'perdu' => 'Perdu',
    ];

    public const COULEURS = [
        'nouveau' => 'secondary',
        'qualifie' => 'info',
        'negociation' => 'warning',
        'converti' => 'success',
        'perdu' => 'danger',
    ];
}
