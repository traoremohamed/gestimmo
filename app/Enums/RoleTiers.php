<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Rôles qu'un tiers peut cumuler.
 */
enum RoleTiers: string
{
    use AvecLibelle;

    case Prospect = 'prospect';
    case Acquereur = 'acquereur';
    case Locataire = 'locataire';
    case Proprietaire = 'proprietaire';
    case Coproprietaire = 'coproprietaire';
    case Fournisseur = 'fournisseur';
    case Garant = 'garant';
    case Client = 'client';

    public const LIBELLES = [
        'prospect' => 'Prospect',
        'acquereur' => 'Acquéreur',
        'locataire' => 'Locataire',
        'proprietaire' => 'Propriétaire / bailleur',
        'coproprietaire' => 'Copropriétaire',
        'fournisseur' => 'Fournisseur',
        'garant' => 'Garant',
        'client' => 'Client meublé',
    ];

    public const COULEURS = [
        'prospect' => 'secondary',
        'acquereur' => 'primary',
        'locataire' => 'info',
        'proprietaire' => 'success',
        'coproprietaire' => 'warning',
        'fournisseur' => 'dark',
        'garant' => 'secondary',
        'client' => 'primary',
    ];
}
