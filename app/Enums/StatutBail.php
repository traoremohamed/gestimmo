<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Cycle de vie d'un bail.
 */
enum StatutBail: string
{
    use AvecLibelle;

    case Brouillon = 'brouillon';
    case Actif = 'actif';
    case Resilie = 'resilie';
    case Termine = 'termine';

    public const LIBELLES = [
        'brouillon' => 'Brouillon',
        'actif' => 'Actif',
        'resilie' => 'Résilié',
        'termine' => 'Terminé',
    ];

    public const COULEURS = [
        'brouillon' => 'secondary',
        'actif' => 'success',
        'resilie' => 'danger',
        'termine' => 'dark',
    ];
}
