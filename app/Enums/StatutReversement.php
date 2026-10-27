<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Reversement au propriétaire.
 */
enum StatutReversement: string
{
    use AvecLibelle;

    case Brouillon = 'brouillon';
    case Valide = 'valide';
    case Paye = 'paye';

    public const LIBELLES = [
        'brouillon' => 'Brouillon',
        'valide' => 'Validé',
        'paye' => 'Payé',
    ];

    public const COULEURS = [
        'brouillon' => 'secondary',
        'valide' => 'primary',
        'paye' => 'success',
    ];
}
