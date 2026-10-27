<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Réclamation.
 */
enum StatutReclamation: string
{
    use AvecLibelle;

    case Ouverte = 'ouverte';
    case EnCours = 'en_cours';
    case Resolue = 'resolue';
    case Fermee = 'fermee';

    public const LIBELLES = [
        'ouverte' => 'Ouverte',
        'en_cours' => 'En cours',
        'resolue' => 'Résolue',
        'fermee' => 'Fermée',
    ];

    public const COULEURS = [
        'ouverte' => 'danger',
        'en_cours' => 'warning',
        'resolue' => 'success',
        'fermee' => 'secondary',
    ];
}
