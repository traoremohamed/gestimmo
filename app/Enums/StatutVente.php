<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Cycle de vie d'un contrat de vente.
 */
enum StatutVente: string
{
    use AvecLibelle;

    case Brouillon = 'brouillon';
    case EnCours = 'en_cours';
    case Soldee = 'soldee';
    case Desistee = 'desistee';
    case Annulee = 'annulee';
    case Transferee = 'transferee';

    public const LIBELLES = [
        'brouillon' => 'Brouillon',
        'en_cours' => 'En cours',
        'soldee' => 'Soldée',
        'desistee' => 'Désistée',
        'annulee' => 'Annulée',
        'transferee' => 'Transférée',
    ];

    public const COULEURS = [
        'brouillon' => 'secondary',
        'en_cours' => 'primary',
        'soldee' => 'success',
        'desistee' => 'danger',
        'annulee' => 'dark',
        'transferee' => 'info',
    ];
}
