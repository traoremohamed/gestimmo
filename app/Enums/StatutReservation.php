<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Réservation (blocage temporaire d'un bien).
 */
enum StatutReservation: string
{
    use AvecLibelle;

    case Active = 'active';
    case Convertie = 'convertie';
    case Liberee = 'liberee';
    case Expiree = 'expiree';

    public const LIBELLES = [
        'active' => 'Active',
        'convertie' => 'Convertie en vente',
        'liberee' => 'Libérée',
        'expiree' => 'Expirée',
    ];

    public const COULEURS = [
        'active' => 'warning',
        'convertie' => 'success',
        'liberee' => 'secondary',
        'expiree' => 'dark',
    ];
}
