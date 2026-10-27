<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Assemblée générale.
 */
enum StatutAssemblee: string
{
    use AvecLibelle;

    case Planifiee = 'planifiee';
    case Convoquee = 'convoquee';
    case Tenue = 'tenue';
    case Annulee = 'annulee';

    public const LIBELLES = [
        'planifiee' => 'Planifiée',
        'convoquee' => 'Convoquée',
        'tenue' => 'Tenue',
        'annulee' => 'Annulée',
    ];

    public const COULEURS = [
        'planifiee' => 'secondary',
        'convoquee' => 'primary',
        'tenue' => 'success',
        'annulee' => 'dark',
    ];
}
