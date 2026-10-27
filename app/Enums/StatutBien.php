<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Statut commercial d'un bien.
 */
enum StatutBien: string
{
    use AvecLibelle;

    case Disponible = 'disponible';
    case Reserve = 'reserve';
    case Vendu = 'vendu';
    case Loue = 'loue';
    case Indisponible = 'indisponible';
    case EnConstruction = 'en_construction';

    public const LIBELLES = [
        'disponible' => 'Disponible',
        'reserve' => 'Réservé',
        'vendu' => 'Vendu',
        'loue' => 'Loué',
        'indisponible' => 'Indisponible',
        'en_construction' => 'En construction',
    ];

    public const COULEURS = [
        'disponible' => 'success',
        'reserve' => 'warning',
        'vendu' => 'dark',
        'loue' => 'info',
        'indisponible' => 'secondary',
        'en_construction' => 'primary',
    ];
}
