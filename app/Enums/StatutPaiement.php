<?php

namespace App\Enums;

use App\Enums\Concerns\AvecLibelle;

/**
 * Statut d'une dette (échéance, frais, loyer, appel de fonds, séjour).
 */
enum StatutPaiement: string
{
    use AvecLibelle;

    case APayer = 'a_payer';
    case Partiel = 'partiel';
    case Paye = 'paye';
    case Annule = 'annule';

    public const LIBELLES = [
        'a_payer' => 'À payer',
        'partiel' => 'Partiel',
        'paye' => 'Payé',
        'annule' => 'Annulé',
    ];

    public const COULEURS = [
        'a_payer' => 'danger',
        'partiel' => 'warning',
        'paye' => 'success',
        'annule' => 'secondary',
    ];
}
