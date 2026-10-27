<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Appel de fonds de copropriété.
 */
enum StatutAppelFonds: string
{
    use AvecLibelle;

    case Brouillon = 'brouillon';
    case Emis = 'emis';
    case Solde = 'solde';
    case Annule = 'annule';

    public const LIBELLES = [
        'brouillon' => 'Brouillon',
        'emis' => 'Émis',
        'solde' => 'Soldé',
        'annule' => 'Annulé',
    ];

    public const COULEURS = [
        'brouillon' => 'secondary',
        'emis' => 'primary',
        'solde' => 'success',
        'annule' => 'dark',
    ];
}
