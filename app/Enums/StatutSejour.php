<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Séjour en résidence meublée.
 */
enum StatutSejour: string
{
    use AvecLibelle;

    case Reserve = 'reserve';
    case Confirme = 'confirme';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Annule = 'annule';

    public const LIBELLES = [
        'reserve' => 'Réservé',
        'confirme' => 'Confirmé',
        'en_cours' => 'En cours',
        'termine' => 'Terminé',
        'annule' => 'Annulé',
    ];

    public const COULEURS = [
        'reserve' => 'warning',
        'confirme' => 'primary',
        'en_cours' => 'info',
        'termine' => 'success',
        'annule' => 'secondary',
    ];
}
