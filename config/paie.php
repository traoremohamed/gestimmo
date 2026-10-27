<?php

/*
|--------------------------------------------------------------------------
| Paie — rubriques par défaut (Côte d'Ivoire, repris de GEST COMM)
|--------------------------------------------------------------------------
| Copiées dans chaque agence à son initialisation, puis modifiables (RH › Rubriques de paie).
| [code, n°, libellé, type, mode de calcul, base, taux, montant, plafond, franchise,
|  imposable, cotisable, compte de charge, compte de tiers, ordre, groupe, proratiser]
*/

return [
    // barème mensuel de l'ITS : [de, à, taux %]
    'bareme_its' => [[0, 75000, 0], [75000, 240000, 16], [240000, 800000, 21], [800000, 2400000, 24], [2400000, 8000000, 28], [8000000, null, 32]],

    // réduction d'impôt pour charges de famille (RICF) par nombre de parts
    'ricf' => ['1' => 0, '1.5' => 5500, '2' => 11000, '2.5' => 16500, '3' => 22000, '3.5' => 27500, '4' => 33000, '4.5' => 38500, '5' => 44000],

    'rubriques' => [
        ['SALBASE', '10', 'Salaire de base', 'GAIN', 'SALAIRE_BASE', null, null, null, null, null, true, true, '6611', null, 10, 'GAINS', false],
        ['JOURS', '11', 'Journées travaillées', 'GAIN', 'JOURS', null, null, null, null, null, true, true, '6611', null, 11, 'GAINS', false],
        ['COMMISSION', '14', 'Commission sur ventes', 'GAIN', 'COMMISSION', 'CA_VENDEUR', null, null, null, null, true, true, '6611', null, 14, 'GAINS', false],
        ['SURSALAIRE', '110', 'Sursalaire', 'GAIN', 'FIXE', null, null, null, null, null, true, true, '6611', null, 20, 'GAINS', true],
        ['ANCIEN', '210', 'Prime d\'ancienneté', 'GAIN', 'ANCIENNETE', 'BASE', 1, null, null, null, true, true, '6612', null, 30, 'GAINS', false],
        ['LOGEMENT', '385', 'Indemnité de logement', 'GAIN', 'FIXE', null, null, null, null, null, true, true, '6618', null, 40, 'GAINS', true],
        ['HS', '395', 'Heures supplémentaires', 'GAIN', 'SAISIE', null, null, null, null, null, true, true, '6613', null, 60, 'GAINS', false],
        ['PRIME_RENDEMENT', '397', 'Prime de rendement', 'GAIN', 'SAISIE', null, null, null, null, null, true, true, '6612', null, 65, 'GAINS', false],
        ['PRIME_EXC', '398', 'Prime exceptionnelle', 'GAIN', 'SAISIE', null, null, null, null, null, true, true, '6612', null, 70, 'GAINS', false],
        ['CNPS_RET_S', '810', 'CNPS — retraite (part salariale)', 'COTISATION_SALARIALE', 'POURCENTAGE', 'BRUT_COTISABLE', 6.3, null, 3375000, null, false, false, null, '4311', 100, 'RETENUES', false],
        ['CMU_S', '815', 'Couverture Maladie Universelle (CMU)', 'COTISATION_SALARIALE', 'FIXE', null, null, 1000, null, null, false, false, null, '4312', 110, 'RETENUES', false],
        ['ITS', '850', 'Impôt sur traitements et salaires (ITS)', 'IMPOT', 'BAREME', 'BRUT_IMPOSABLE', null, null, null, null, false, false, null, '4471', 120, 'RETENUES', false],
        ['TRANSPORT', '204', 'Indemnité de transport', 'GAIN', 'FIXE', null, null, 30000, null, 30000, true, true, '6618', null, 200, 'NON_IMPOSABLES', true],
        ['AVANCE', '600', 'Remboursement d\'avance / prêt', 'RETENUE', 'AVANCE', null, null, null, null, null, false, false, null, '421', 330, 'AUTRES', false],
        ['RETENUE_DIV', '610', 'Retenue diverse', 'RETENUE', 'SAISIE', null, null, null, null, null, false, false, null, '422', 340, 'AUTRES', false],
        ['CNPS_RET_P', '910', 'CNPS — retraite (part patronale)', 'COTISATION_PATRONALE', 'POURCENTAGE', 'BRUT_COTISABLE', 7.7, null, 3375000, null, false, false, '6641', '4311', 400, 'PATRONAL', false],
        ['CNPS_PF', '920', 'CNPS — prestations familiales', 'COTISATION_PATRONALE', 'POURCENTAGE', 'BRUT_COTISABLE', 5, null, 75000, null, false, false, '6641', '4311', 410, 'PATRONAL', false],
        ['CNPS_MAT', '925', 'CNPS — assurance maternité', 'COTISATION_PATRONALE', 'POURCENTAGE', 'BRUT_COTISABLE', 0.75, null, 75000, null, false, false, '6641', '4311', 420, 'PATRONAL', false],
        ['CNPS_AT', '930', 'CNPS — accidents du travail', 'COTISATION_PATRONALE', 'POURCENTAGE', 'BRUT_COTISABLE', 2, null, 75000, null, false, false, '6641', '4311', 430, 'PATRONAL', false],
        ['CMU_P', '935', 'CMU — part patronale', 'COTISATION_PATRONALE', 'FIXE', null, null, 1000, null, null, false, false, '6641', '4312', 440, 'PATRONAL', false],
        ['IS_EMP', '940', 'Impôt sur salaires (part employeur)', 'COTISATION_PATRONALE', 'POURCENTAGE', 'BRUT_IMPOSABLE', 1.2, null, null, null, false, false, '641', '4472', 450, 'PATRONAL', false],
        ['TA', '945', 'Taxe d\'apprentissage', 'COTISATION_PATRONALE', 'POURCENTAGE', 'BRUT_IMPOSABLE', 0.4, null, null, null, false, false, '641', '4472', 460, 'PATRONAL', false],
        ['FPC', '950', 'Taxe de formation professionnelle continue', 'COTISATION_PATRONALE', 'POURCENTAGE', 'BRUT_IMPOSABLE', 0.6, null, null, null, false, false, '641', '4472', 470, 'PATRONAL', false],
    ],

    // niveaux de relance par défaut : [niveau, libellé, jours de retard, canal, objet, message]
    'relances' => [
        [1, 'Rappel amiable', 3, 'sms', 'Rappel de paiement',
            'Bonjour {nom}, sauf erreur de notre part, un montant de {montant} reste dû depuis {retard} jours. Merci de régulariser. {agence}'],
        [2, 'Première relance', 15, 'email', 'Relance : sommes restant dues',
            "Bonjour {nom},\n\nMalgré notre précédent rappel, les sommes suivantes restent impayées :\n{details}\n\nTotal dû : {montant}. Nous vous remercions de procéder au règlement sous 8 jours ou de nous contacter pour convenir d'un échéancier.\n\n{agence}"],
        [3, 'Deuxième relance', 30, 'courrier', 'Deuxième relance avant mise en demeure',
            "Madame, Monsieur {nom},\n\nNous constatons que votre compte présente un solde impayé de {montant} ({retard} jours de retard).\n{details}\n\nÀ défaut de règlement sous 8 jours, nous serons contraints de vous adresser une mise en demeure.\n\n{agence}"],
        [4, 'Mise en demeure', 60, 'courrier', 'Mise en demeure de payer',
            "Madame, Monsieur {nom},\n\nPar la présente, nous vous mettons en demeure de régler sous huitaine la somme de {montant}, correspondant à :\n{details}\n\nÀ défaut, nous engagerons sans autre avis les procédures de recouvrement prévues par le contrat et la loi, les frais restant à votre charge.\n\n{agence}"],
    ],
];
