<?php

/*
|--------------------------------------------------------------------------
| Comptabilité SYSCOHADA révisé — plan, journaux et règles d'imputation par défaut
|--------------------------------------------------------------------------
| Copiés dans chaque agence à son initialisation, puis modifiables
| (Comptabilité › Plan comptable / Règles d'imputation).
|
| Principe (comptabilité de trésorerie) : chaque encaissement, dépense, reversement,
| paiement de salaire produit une écriture équilibrée ; une annulation est contre-passée.
| Les fonds détenus pour le compte de tiers ne sont pas des produits de l'agence :
| loyers des biens en gestion (4671) et fonds des syndicats de copropriété (4672).
*/

return [

    // numéro => [libellé, type, lettrable]
    'plan' => [
        '101' => ['Capital social', 'CAPITAUX'], '121' => ['Report à nouveau créditeur', 'CAPITAUX'], '129' => ['Report à nouveau débiteur', 'CAPITAUX'],
        '131' => ['Résultat net : bénéfice', 'CAPITAUX'], '139' => ['Résultat net : perte', 'CAPITAUX'], '162' => ['Emprunts auprès des établissements de crédit', 'CAPITAUX'],
        '165' => ['Dépôts et cautionnements reçus (locataires)', 'CAPITAUX', true],
        '22' => ['Terrains', 'IMMOBILISATION'], '231' => ['Bâtiments', 'IMMOBILISATION'], '2441' => ['Matériel de bureau', 'IMMOBILISATION'],
        '2442' => ['Matériel informatique', 'IMMOBILISATION'], '2444' => ['Mobilier', 'IMMOBILISATION'], '245' => ['Matériel de transport', 'IMMOBILISATION'],
        '28' => ['Amortissements des immobilisations', 'IMMOBILISATION'],
        '37' => ['Stocks de programmes immobiliers', 'STOCK'],
        '4011' => ['Fournisseurs', 'TIERS', true], '4111' => ['Clients acquéreurs', 'TIERS', true], '4112' => ['Locataires', 'TIERS', true],
        '4113' => ['Clients résidences meublées', 'TIERS', true], '4191' => ['Clients, avances et acomptes reçus', 'TIERS', true],
        '421' => ['Personnel, avances et acomptes', 'TIERS', true], '422' => ['Personnel, rémunérations dues', 'TIERS', true],
        '4311' => ['CNPS', 'TIERS'], '4312' => ['CMU', 'TIERS'], '4431' => ['TVA facturée', 'TIERS'], '4452' => ['TVA récupérable', 'TIERS'],
        '4471' => ['Impôts retenus à la source (ITS)', 'TIERS'], '4472' => ['Impôts et taxes sur salaires (part employeur)', 'TIERS'],
        '4671' => ['Propriétaires mandants (gestion locative)', 'TIERS', true], '4672' => ['Syndicats de copropriétaires (fonds gérés)', 'TIERS', true],
        '471' => ['Comptes d\'attente', 'TIERS'],
        '5211' => ['Banque', 'TRESORERIE'], '5521' => ['Mobile money', 'TRESORERIE'], '5711' => ['Caisse', 'TRESORERIE'], '585' => ['Virements de fonds', 'TRESORERIE'],
        '6051' => ['Eau', 'CHARGE'], '6052' => ['Électricité', 'CHARGE'], '6055' => ['Fournitures de bureau', 'CHARGE'], '622' => ['Locations et charges locatives', 'CHARGE'],
        '624' => ['Entretien, réparations et maintenance', 'CHARGE'], '6241' => ['Travaux sur biens', 'CHARGE'], '625' => ['Primes d\'assurance', 'CHARGE'],
        '627' => ['Publicité et relations publiques', 'CHARGE'], '628' => ['Télécommunications', 'CHARGE'], '631' => ['Frais bancaires', 'CHARGE'],
        '632' => ['Honoraires', 'CHARGE'], '6383' => ['Gardiennage et sécurité', 'CHARGE'], '6388' => ['Charges externes diverses', 'CHARGE'], '641' => ['Impôts et taxes', 'CHARGE'],
        '6588' => ['Charges diverses', 'CHARGE'], '6611' => ['Salaires et commissions', 'CHARGE'], '6612' => ['Primes et gratifications', 'CHARGE'],
        '6613' => ['Heures supplémentaires', 'CHARGE'], '6618' => ['Autres indemnités', 'CHARGE'], '6641' => ['Charges sociales', 'CHARGE'], '681' => ['Dotations aux amortissements', 'CHARGE'],
        '7011' => ['Ventes de biens immobiliers', 'PRODUIT'], '7061' => ['Honoraires, frais de dossier et commissions', 'PRODUIT'], '7062' => ['Loyers des biens propres', 'PRODUIT'],
        '7063' => ['Hébergement (résidences meublées)', 'PRODUIT'], '7064' => ['Honoraires de syndic', 'PRODUIT'], '7588' => ['Produits divers (pénalités)', 'PRODUIT'],
    ],

    // code => [libellé, type]
    'journaux' => [
        'CA' => ['Caisse', 'TRESORERIE'], 'BQ' => ['Banque', 'TRESORERIE'], 'MM' => ['Mobile money', 'TRESORERIE'],
        'PA' => ['Paie', 'PAIE'], 'OD' => ['Opérations diverses', 'OD'], 'AN' => ['À-nouveaux', 'OD'],
    ],

    // opération => [clé => [libellé, compte, journal]]
    'schemas' => [
        'TRESORERIE' => [
            'ESPECES' => ['Espèces', '5711', 'CA'], 'CHEQUE' => ['Chèque', '5211', 'BQ'], 'VIREMENT' => ['Virement', '5211', 'BQ'],
            'MOBILE_MONEY' => ['Mobile money', '5521', 'MM'], 'CARTE' => ['Carte bancaire', '5211', 'BQ'], 'AUTRE' => ['Autre mode', '5711', 'CA'],
            'AVOIR' => ['Compensation sur l\'avoir du locataire sortant (garanties)', '4191', 'OD'],
        ],
        'ENCAISSEMENT' => [
            'echeance' => ['Échéance de vente', '7011', null], 'vente_frais' => ['Frais annexes de vente', '7061', null],
            'loyer' => ['Loyer d\'un bien propre', '7062', null], 'loyer_mandat' => ['Loyer d\'un bien en gestion', '4671', null],
            'avance' => ['Avance sur loyer (bien propre)', '7062', null], 'avance_mandat' => ['Avance sur loyer (bien en gestion)', '4671', null],
            'depot_garantie' => ['Dépôt de garantie', '165', null], 'frais_agence' => ['Frais d\'agence', '7061', null],
            'penalite' => ['Pénalité de retard (bien propre)', '7588', null], 'penalite_mandat' => ['Pénalité de retard (bien en gestion)', '4671', null],
            'regularisation' => ['Régularisation de charges', '7062', null], 'appel_ligne' => ['Appel de fonds de copropriété', '4672', null],
            'sejour' => ['Séjour en résidence meublée', '7063', null],
            'caution_compteur' => ['Caution de compteur SODECI / CIE', '165', null],
            'retenue' => ['Retenue de sortie (bien propre : réparations, factures)', '7588', null], 'retenue_mandat' => ['Retenue de sortie (bien en gestion)', '4671', null],
        ],
        'DEPENSE' => [
            'ENTRETIEN' => ['Entretien et nettoyage', '624', null], 'EAU' => ['Eau', '6051', null], 'ELECTRICITE' => ['Électricité', '6052', null],
            'GARDIENNAGE' => ['Gardiennage', '6383', null], 'ASSURANCE' => ['Assurance', '625', null], 'ASCENSEUR' => ['Ascenseur', '624', null],
            'HONORAIRES' => ['Honoraires', '632', null], 'TRAVAUX' => ['Travaux', '6241', null], 'FOURNITURES' => ['Fournitures', '6055', null],
            'TAXES' => ['Impôts et taxes', '641', null], 'AUTRE' => ['Autres charges', '6588', null],
            'REMBOURSEMENT' => ['Remboursement client (désistement)', '7011', null], 'REVERSEMENT' => ['Reversement propriétaire', '4671', null],
            'COPROPRIETE' => ['Dépense payée sur les fonds d\'une copropriété', '4672', null], 'REFACTURABLE' => ['Dépense pour le compte d\'un propriétaire', '4671', null],
            'RESTITUTION' => ['Restitution des garanties au locataire sortant', '4191', null],
            'COMPTEUR_CIE' => ['Remboursement du crédit de compteur CIE (charge agence)', '6052', null],
        ],
        'REVERSEMENT' => [
            'mandant' => ['Compte du propriétaire', '4671', null], 'commission' => ['Commission de gestion', '7061', null],
        ],
        'PAIE' => [
            'net' => ['Salaires nets à payer', '422', 'PA'], 'avance' => ['Avances au personnel', '421', 'PA'],
        ],
    ],

    // catégories de dépense ⇒ classe de compte pour le tableau des charges (B.I)
    'classes' => [1 => 'Capitaux', 2 => 'Immobilisations', 3 => 'Stocks', 4 => 'Tiers', 5 => 'Trésorerie', 6 => 'Charges', 7 => 'Produits'],
];
