<?php

/*
|--------------------------------------------------------------------------
| Configuration métier de la plateforme immobilière
|--------------------------------------------------------------------------
| - modules     : domaines activables par agence ; chaque module déclare son
|                 menu, ses sous-menus (= nom de route / url) et ses permissions.
|                 Le seeder ImmobilierSeeder les crée dans menu / sousmenu / permissions.
| - parametres  : paramètres métier par agence (valeur par défaut ici,
|                 surchargée dans la table parametres).
| - referentiels: listes de valeurs par agence, initialisées à la création de l'agence.
| - frais       : frais annexes proposés par défaut à une nouvelle agence.
*/

return [

    'modules' => [

        'patrimoine' => [
            'libelle' => 'Patrimoine',
            'description' => 'Programmes, lotissements, immeubles et biens (terrains, villas, appartements, bureaux…).',
            'icone' => 'ti ti-building-estate',
            'priorite' => 10,
            'obligatoire' => true,
            'menus' => [
                ['programmes', 'Programmes / sites', 1, ['programmes-index' => 'Voir', 'programmes-create' => 'Créer', 'programmes-edit' => 'Editer', 'programmes-delete' => 'Supprimer']],
                ['lotissements', 'Lotissements', 2, ['lotissements-index' => 'Voir', 'lotissements-create' => 'Créer', 'lotissements-edit' => 'Editer', 'lotissements-delete' => 'Supprimer']],
                ['immeubles', 'Immeubles', 3, ['immeubles-index' => 'Voir', 'immeubles-create' => 'Créer', 'immeubles-edit' => 'Editer', 'immeubles-delete' => 'Supprimer']],
                ['biens', 'Biens', 4, ['biens-index' => 'Voir', 'biens-create' => 'Créer', 'biens-edit' => 'Editer', 'biens-delete' => 'Supprimer', 'biens-generer' => 'Générer en masse', 'biens-publier' => 'Publier']],
            ],
        ],

        'tiers' => [
            'libelle' => 'Tiers & CRM',
            'description' => 'Clients, prospects, locataires, propriétaires, copropriétaires, fournisseurs ; rendez-vous et réclamations.',
            'icone' => 'ti ti-users',
            'priorite' => 20,
            'obligatoire' => true,
            'menus' => [
                ['tiers', 'Tiers (clients, propriétaires…)', 1, ['tiers-index' => 'Voir', 'tiers-create' => 'Créer', 'tiers-edit' => 'Editer', 'tiers-delete' => 'Supprimer']],
                ['prospects', 'Prospects', 2, ['prospects-index' => 'Voir', 'prospects-create' => 'Créer', 'prospects-edit' => 'Editer', 'prospects-tous' => 'Voir les prospects de tous les commerciaux']],
                ['rendezvous', 'Rendez-vous', 3, ['rendezvous-index' => 'Voir', 'rendezvous-create' => 'Créer', 'rendezvous-edit' => 'Editer', 'rendezvous-tous' => 'Voir les rendez-vous de tous les agents']],
                ['reclamations', 'Réclamations', 4, ['reclamations-index' => 'Voir', 'reclamations-create' => 'Créer', 'reclamations-edit' => 'Traiter']],
                ['demandescontact', 'Demandes de contact', 5, ['demandescontact-index' => 'Voir', 'demandescontact-edit' => 'Traiter']],
            ],
        ],

        'vente' => [
            'libelle' => 'Ventes',
            'description' => 'Réservations, contrats de vente, frais annexes, échéanciers, désistements et remboursements.',
            'icone' => 'ti ti-home-dollar',
            'priorite' => 30,
            'menus' => [
                ['reservations', 'Réservations', 1, ['reservations-index' => 'Voir', 'reservations-create' => 'Créer', 'reservations-edit' => 'Libérer / convertir']],
                ['ventes', 'Contrats de vente', 2, ['ventes-index' => 'Voir', 'ventes-create' => 'Créer', 'ventes-edit' => 'Editer', 'ventes-valider' => 'Valider', 'ventes-desister' => 'Désister', 'ventes-rembourser' => 'Rembourser']],
                ['echeances', 'Échéances & impayés', 3, ['echeances-index' => 'Voir']],
            ],
        ],

        'location' => [
            'libelle' => 'Gestion locative',
            'description' => 'Mandats de gestion, baux, loyers et quittances, impayés, reversements aux propriétaires.',
            'icone' => 'ti ti-key',
            'priorite' => 40,
            'menus' => [
                ['mandats', 'Mandats de gestion', 1, ['mandats-index' => 'Voir', 'mandats-create' => 'Créer', 'mandats-edit' => 'Editer']],
                ['baux', 'Baux', 2, ['baux-index' => 'Voir', 'baux-create' => 'Créer', 'baux-edit' => 'Editer', 'baux-activer' => 'Activer', 'baux-resilier' => 'Résilier', 'baux-sortie' => 'Décompte de sortie', 'baux-restituer' => 'Restituer les garanties']],
                ['loyers', 'Loyers & quittances', 3, ['loyers-index' => 'Voir', 'loyers-generer' => 'Générer les loyers']],
                ['reversements', 'Reversements propriétaires', 4, ['reversements-index' => 'Voir', 'reversements-create' => 'Créer', 'reversements-edit' => 'Valider / payer']],
            ],
        ],

        'syndic' => [
            'libelle' => 'Syndic de copropriété',
            'description' => 'Copropriétés, lots et tantièmes, budgets, appels de fonds, assemblées générales.',
            'icone' => 'ti ti-building-community',
            'priorite' => 50,
            'menus' => [
                ['coproprietes', 'Copropriétés', 1, ['coproprietes-index' => 'Voir', 'coproprietes-create' => 'Créer', 'coproprietes-edit' => 'Editer']],
                ['appelsfonds', 'Appels de fonds', 2, ['appelsfonds-index' => 'Voir', 'appelsfonds-create' => 'Créer', 'appelsfonds-emettre' => 'Emettre']],
                ['assemblees', 'Assemblées générales', 3, ['assemblees-index' => 'Voir', 'assemblees-create' => 'Créer', 'assemblees-edit' => 'Editer']],
            ],
        ],

        'maintenance' => [
            'libelle' => 'Interventions & travaux',
            'description' => 'Signalement, suivi et coût des interventions sur les biens et parties communes.',
            'icone' => 'ti ti-tool',
            'priorite' => 55,
            'menus' => [
                ['interventions', 'Interventions', 1, ['interventions-index' => 'Voir', 'interventions-create' => 'Créer', 'interventions-edit' => 'Editer']],
            ],
        ],

        'meuble' => [
            'libelle' => 'Résidences meublées',
            'description' => 'Séjours à la nuitée, disponibilités et facturation.',
            'icone' => 'ti ti-bed',
            'priorite' => 60,
            'menus' => [
                ['sejours', 'Séjours', 1, ['sejours-index' => 'Voir', 'sejours-create' => 'Créer', 'sejours-edit' => 'Editer']],
            ],
        ],

        'finance' => [
            'libelle' => 'Caisse & finances',
            'description' => 'Encaissements affectés, reçus numérotés, dépenses, journal de caisse.',
            'icone' => 'ti ti-cash',
            'priorite' => 70,
            'obligatoire' => true,
            'menus' => [
                ['encaissements', 'Encaissements', 1, ['encaissements-index' => 'Voir', 'encaissements-create' => 'Encaisser', 'encaissements-annuler' => 'Annuler']],
                ['depenses', 'Dépenses', 2, ['depenses-index' => 'Voir', 'depenses-create' => 'Créer', 'depenses-edit' => 'Editer']],
                ['caisse', 'Journal de caisse', 3, ['caisse-index' => 'Voir']],
            ],
        ],

        'marketing' => [
            'libelle' => 'Marketing',
            'description' => 'Campagnes e-mail et SMS ciblées sur les tiers.',
            'icone' => 'ti ti-speakerphone',
            'priorite' => 80,
            'menus' => [
                ['campagnes', 'Campagnes', 1, ['campagnes-index' => 'Voir', 'campagnes-create' => 'Créer', 'campagnes-envoyer' => 'Envoyer']],
            ],
        ],

        'vitrine' => [
            'libelle' => 'Site vitrine',
            'description' => 'Site public de l\'agence : annonces, actualités, slides, témoignages, demandes de visite.',
            'icone' => 'ti ti-world',
            'priorite' => 90,
            'menus' => [
                ['siteweb', 'Site web : réglages & pages', 0, ['siteweb-index' => 'Voir', 'siteweb-edit' => 'Modifier', 'siteweb-publier' => 'Publier']],
                ['publications', 'Actualités & articles', 1, ['publications-index' => 'Voir', 'publications-create' => 'Créer', 'publications-edit' => 'Editer']],
                ['slides', 'Slides', 2, ['slides-index' => 'Voir', 'slides-create' => 'Créer', 'slides-edit' => 'Editer']],
                ['temoignages', 'Témoignages', 3, ['temoignages-index' => 'Voir', 'temoignages-create' => 'Créer', 'temoignages-edit' => 'Editer']],
            ],
        ],

        'pilotage' => [
            'libelle' => 'Tableaux de bord',
            'description' => 'Indicateurs et états : chiffre d\'affaires, encaissements, impayés, occupation, stock.',
            'icone' => 'ti ti-chart-bar',
            'priorite' => 100,
            'obligatoire' => true,
            'menus' => [
                ['etats', 'États & rapports', 1, ['etats-index' => 'Voir']],
                ['objectifs', 'Objectifs commerciaux', 2, ['objectifs-index' => 'Voir', 'objectifs-create' => 'Créer', 'objectifs-edit' => 'Editer']],
            ],
        ],

        'recouvrement' => [
            'libelle' => 'Recouvrement & relances',
            'description' => 'Dossiers de recouvrement des impayés (ventes, loyers, charges, séjours), relances automatiques graduées, promesses de paiement, mises en demeure.',
            'icone' => 'ti ti-alarm',
            'priorite' => 75,
            'menus' => [
                ['recouvrements', 'Dossiers de recouvrement', 1, ['recouvrements-index' => 'Voir', 'recouvrements-edit' => 'Traiter', 'recouvrements-relancer' => 'Relancer', 'recouvrements-tous' => 'Voir les dossiers de tous les gestionnaires']],
                ['reglesrelance', 'Niveaux de relance', 2, ['reglesrelance-index' => 'Voir', 'reglesrelance-edit' => 'Paramétrer']],
            ],
        ],

        'rh' => [
            'libelle' => 'RH & personnel',
            'description' => 'Dossiers du personnel, contrats, carrière, présences, congés et absences avec validation, avances et prêts.',
            'icone' => 'ti ti-id-badge-2',
            'priorite' => 85,
            'menus' => [
                ['employes', 'Personnel', 1, ['employes-index' => 'Voir', 'employes-create' => 'Créer', 'employes-edit' => 'Editer']],
                ['absences', 'Congés & absences', 2, ['absences-index' => 'Voir', 'absences-create' => 'Demander', 'absences-valider' => 'Valider / refuser']],
                ['presences', 'Présences', 3, ['presences-index' => 'Voir', 'presences-edit' => 'Pointer']],
                ['avances', 'Avances & prêts', 4, ['avances-index' => 'Voir', 'avances-create' => 'Accorder']],
            ],
        ],

        'paie' => [
            'libelle' => 'Paie',
            'description' => 'Bulletins de paie (CNPS, CMU, ITS, parts fiscales), commissions des commerciaux, validation, paiement, livre de paie.',
            'icone' => 'ti ti-report-money',
            'priorite' => 86,
            'menus' => [
                ['paie', 'Périodes & bulletins', 1, ['paie-index' => 'Voir', 'paie-calculer' => 'Calculer', 'paie-valider' => 'Valider', 'paie-payer' => 'Payer']],
                ['rubriques', 'Rubriques de paie', 2, ['rubriques-index' => 'Voir', 'rubriques-edit' => 'Paramétrer']],
            ],
        ],

        'comptabilite' => [
            'libelle' => 'Comptabilité',
            'description' => 'Comptabilité SYSCOHADA générée automatiquement : journaux, grand livre, balance, bilan, compte de résultat, clôture.',
            'icone' => 'ti ti-calculator',
            'priorite' => 87,
            'menus' => [
                ['ecritures', 'Écritures & journaux', 1, ['ecritures-index' => 'Voir', 'ecritures-create' => 'Saisir une écriture', 'ecritures-generer' => 'Générer depuis les opérations']],
                ['comptabilite', 'États comptables', 2, ['comptabilite-index' => 'Voir', 'comptabilite-cloturer' => 'Clôturer un exercice']],
                ['plancomptable', 'Plan comptable & imputations', 3, ['plancomptable-index' => 'Voir', 'plancomptable-edit' => 'Paramétrer']],
            ],
        ],

        'analyse' => [
            'libelle' => 'Analyse & décision',
            'description' => 'Informatique décisionnelle : indicateurs comparés, prévisions d\'encaissement, risque clients, rentabilité, recommandations et simulations.',
            'icone' => 'ti ti-chart-dots-3',
            'priorite' => 95,
            'menus' => [
                ['analyse', 'Tableau décisionnel', 1, ['analyse-index' => 'Voir']],
                ['simulateur', 'Simulateur', 2, ['simulateur-index' => 'Voir']],
            ],
        ],

        'validation' => [
            'libelle' => 'Validations',
            'description' => 'Circuits de validation paramétrables (approbation en une ou plusieurs étapes avant exécution) : ventes, baux, sorties, reversements, dépenses, annulations, RH, paie, droits.',
            'icone' => 'ti ti-checks',
            'priorite' => 104,
            'obligatoire' => true,
            'menus' => [
                ['validations', 'Validations à décider', 1, ['validations-index' => 'Voir et décider', 'validations-toutes' => 'Piloter toutes les demandes']],
                ['circuits', 'Circuits de validation', 2, ['circuits-index' => 'Voir', 'circuits-edit' => 'Paramétrer', 'circuits-approuver' => 'Approuver et activer']],
            ],
        ],

        'ged' => [
            'libelle' => 'GED — Documents',
            'description' => 'Gestion électronique de documents : tout fichier (PDF, images, Excel, Word…) rattaché aux dossiers, archivage automatique des documents émis, signature QR, versions.',
            'icone' => 'ti ti-folders',
            'priorite' => 105,
            'menus' => [
                ['ged', 'Documents (GED)', 1, ['ged-index' => 'Voir', 'ged-create' => 'Ajouter', 'ged-delete' => 'Supprimer', 'ged-confidentiel' => 'Documents confidentiels']],
            ],
        ],

        'espace' => [
            'libelle' => 'Espace client',
            'description' => 'Accès des acquéreurs, locataires, propriétaires et copropriétaires à leurs contrats et paiements.',
            'icone' => 'ti ti-user-circle',
            'priorite' => 110,
            'menus' => [
                ['moncompte', 'Mon espace', 1, ['moncompte-index' => 'Voir']],
            ],
        ],
    ],

    /*
    | Menus d'administration (comme le SIH et MARIEATTIEKESAAS) : [route, libellé, priorité, [permissions], plateforme ?]
    |  - ADMINISTRATION : complète celui du socle (utilisateurs, profils, droits, journal) avec l'agence et ses modules ;
    |  - PARAMETRAGE    : réglages métier de l'agence ;
    |  - PLATEFORME     : super administrateur uniquement (agences clientes, sauvegardes, sécurité).
    */
    'menus_administration' => [
        ['monagence', 'Mon agence', 4, ['monagence-edit' => 'Modifier les informations']],
        ['configuration/modules', 'Modules activés', 5, ['modules-index' => 'Voir', 'modules-edit' => 'Activer / désactiver']],
    ],

    'menus_parametrage' => [
        ['parametres', 'Paramètres métier', 1, ['parametres-edit' => 'Modifier']],
        ['referentiels', 'Listes de valeurs', 2, ['referentiels-index' => 'Voir', 'referentiels-edit' => 'Modifier']],
        ['fraistypes', 'Frais annexes', 3, ['fraistypes-index' => 'Voir', 'fraistypes-edit' => 'Modifier']],
        ['integrations', 'Intégrations & messages', 4, ['integrations-index' => 'Voir', 'integrations-edit' => 'Paramétrer']],
    ],

    'menus_plateforme' => [
        ['plateforme', 'Tableau de bord plateforme', 1, ['plateforme-index' => 'Voir'], true],
        ['agences', 'Agences clientes', 2, ['agences-index' => 'Voir', 'agences-create' => 'Créer', 'agences-edit' => 'Modifier'], true],
        ['plateforme/sauvegardes', 'Sauvegardes', 7, ['sauvegardes-index' => 'Voir', 'sauvegardes-gerer' => 'Configurer / lancer', 'sauvegardes-telecharger' => 'Télécharger une archive'], true],
        ['plateforme/securite', 'Sécurité', 8, ['securite-index' => 'Voir le diagnostic'], true],
    ],

    /*
    | Profils métier créés par le seeder : [code, permissions]
    | '*' = toutes les permissions du module, 'module:xxx' = tout le module xxx.
    */
    'profils' => [
        'DIRECTEUR D\'AGENCE' => ['DIR', ['module:*', 'monagence-edit', 'modules-index', 'modules-edit', 'parametres-edit', 'referentiels-index', 'referentiels-edit', 'fraistypes-index', 'fraistypes-edit', 'integrations-index', 'integrations-edit', 'users-index', 'users-create', 'users-edit', 'audit-index']],
        'COMMERCIAL' => ['COM', ['validations-index', 'programmes-index', 'lotissements-index', 'immeubles-index', 'biens-index', 'tiers-index', 'tiers-create', 'tiers-edit', 'prospects-index', 'prospects-create', 'prospects-edit', 'rendezvous-index', 'rendezvous-create', 'rendezvous-edit', 'reservations-index', 'reservations-create', 'reservations-edit', 'ventes-index', 'ventes-create', 'ventes-edit', 'echeances-index', 'etats-index']],
        'GESTIONNAIRE LOCATIF' => ['GLOC', ['validations-index', 'biens-index', 'biens-edit', 'tiers-index', 'tiers-create', 'tiers-edit', 'rendezvous-index', 'rendezvous-create', 'rendezvous-edit', 'reclamations-index', 'reclamations-create', 'reclamations-edit', 'module:location', 'module:maintenance', 'module:meuble', 'etats-index', 'ged-index', 'ged-create']],
        'GESTIONNAIRE SYNDIC' => ['SYND', ['validations-index', 'biens-index', 'immeubles-index', 'tiers-index', 'tiers-create', 'tiers-edit', 'reclamations-index', 'reclamations-edit', 'module:syndic', 'module:maintenance', 'depenses-index', 'depenses-create', 'etats-index']],
        'CAISSIER / COMPTABLE' => ['COMPTA', ['validations-index', 'tiers-index', 'ventes-index', 'echeances-index', 'baux-index', 'loyers-index', 'appelsfonds-index', 'sejours-index', 'module:finance', 'reversements-index', 'reversements-edit', 'ventes-rembourser', 'etats-index', 'module:comptabilite', 'recouvrements-index', 'paie-index', 'paie-payer', 'baux-restituer', 'ged-index', 'ged-create']],
        'CHARGE DE RECOUVREMENT' => ['RECOUV', ['validations-index', 'tiers-index', 'ventes-index', 'echeances-index', 'baux-index', 'loyers-index', 'appelsfonds-index', 'module:recouvrement', 'encaissements-index', 'rendezvous-index', 'rendezvous-create', 'etats-index']],
        'RESPONSABLE RH' => ['RH', ['validations-index', 'module:rh', 'module:paie', 'etats-index']],
        'EMPLOYE' => ['EMP', ['absences-index', 'absences-create']],
        'CLIENT' => ['CLIENT', ['moncompte-index']],
    ],

    /*
    | Paramètres métier : cle => [libellé, type (texte|entier|decimal|pourcentage|booleen|heure), défaut, groupe]
    */
    'parametres' => [
        'general.devise' => ['Devise affichée', 'texte', 'FCFA', 'Général'],
        'general.signataire' => ['Signataire des documents', 'texte', 'La Direction', 'Général'],
        'general.mentions_recu' => ['Mentions en pied de reçu', 'texte', '', 'Général'],

        'vente.taux_acompte' => ['Acompte par défaut (% du prix)', 'pourcentage', 30, 'Ventes'],
        'vente.nombre_echeances' => ['Nombre d\'échéances par défaut', 'entier', 12, 'Ventes'],
        'vente.periodicite_mois' => ['Périodicité des échéances (mois)', 'entier', 1, 'Ventes'],
        'vente.duree_reservation_jours' => ['Durée de validité d\'une réservation (jours)', 'entier', 7, 'Ventes'],
        'vente.taux_retenue_desistement' => ['Retenue sur désistement (% des sommes versées)', 'pourcentage', 10, 'Ventes'],
        'vente.taux_commission' => ['Commission commerciale (% du prix)', 'pourcentage', 0, 'Ventes'],

        'location.jour_echeance' => ['Jour d\'échéance des loyers', 'entier', 5, 'Gestion locative'],
        'location.mois_caution' => ['Dépôt de garantie (mois de loyer)', 'entier', 2, 'Gestion locative'],
        'location.mois_avance' => ['Avance sur loyer (mois)', 'entier', 1, 'Gestion locative'],
        'location.mois_frais_agence' => ['Frais d\'agence (mois de loyer)', 'entier', 1, 'Gestion locative'],
        'location.taux_commission_gestion' => ['Commission de gestion (% des loyers encaissés)', 'pourcentage', 10, 'Gestion locative'],
        'location.taux_penalite_retard' => ['Pénalité de retard (% du loyer)', 'pourcentage', 0, 'Gestion locative'],
        'location.delai_grace_jours' => ['Délai de grâce avant pénalité (jours)', 'entier', 5, 'Gestion locative'],
        'location.duree_bail_mois' => ['Durée de bail par défaut (mois)', 'entier', 12, 'Gestion locative'],

        // sortie du locataire : décompte des garanties
        'sortie.deduire_reparations' => ['Déduire les réparations constatées à l\'état des lieux de sortie', 'booleen', true, 'Sortie du locataire'],
        'sortie.deduire_impayes' => ['Déduire les loyers et charges impayés', 'booleen', true, 'Sortie du locataire'],
        'sortie.cautions_compteurs' => ['Gérer les cautions SODECI / CIE perçues par l\'agence', 'booleen', true, 'Sortie du locataire'],
        'sortie.credit_compteur_cie' => ['Rembourser le crédit de compteur CIE (compteur pris à crédit)', 'booleen', true, 'Sortie du locataire'],
        'sortie.credit_compteur_charge' => ['Crédit de compteur CIE remboursé à la charge de', 'choix', 'proprietaire', 'Sortie du locataire',
            ['proprietaire' => 'Propriétaire (déduit du reversement ; agence si bien propre)', 'agence' => 'Agence']],
        'sortie.preuve_obligatoire' => ['Exiger les justificatifs (reçus CIE, dernières factures) avant validation', 'booleen', true, 'Sortie du locataire'],
        'sortie.delai_restitution_jours' => ['Délai de restitution des garanties après la sortie (jours, alerte)', 'entier', 30, 'Sortie du locataire'],

        'ged.taille_max_mo' => ['Taille maximale d\'un fichier (Mo)', 'entier', 20, 'GED'],

        'syndic.total_tantiemes' => ['Total des tantièmes par défaut', 'entier', 1000, 'Syndic'],
        'syndic.taux_fonds_travaux' => ['Cotisation fonds de travaux (% du budget)', 'pourcentage', 5, 'Syndic'],
        'syndic.delai_paiement_jours' => ['Délai de paiement d\'un appel de fonds (jours)', 'entier', 30, 'Syndic'],
        'syndic.appels_par_an' => ['Nombre d\'appels de fonds par exercice', 'entier', 4, 'Syndic'],

        'meuble.heure_arrivee' => ['Heure d\'arrivée', 'heure', '14:00', 'Résidences meublées'],
        'meuble.heure_depart' => ['Heure de départ', 'heure', '12:00', 'Résidences meublées'],
        'meuble.taux_acompte' => ['Acompte à la réservation (%)', 'pourcentage', 50, 'Résidences meublées'],

        'recouvrement.relances_automatiques' => ['Envoyer automatiquement les relances (tâche quotidienne)', 'booleen', true, 'Recouvrement'],
        'recouvrement.seuil_minimum' => ['Montant minimum déclenchant une relance', 'decimal', 1000, 'Recouvrement'],
        'recouvrement.delai_entre_relances_jours' => ['Délai minimum entre deux relances d\'un même dossier (jours)', 'entier', 7, 'Recouvrement'],
        'recouvrement.alerte_jours_avant' => ['Alerter avant échéance (jours)', 'entier', 5, 'Recouvrement'],

        'rh.jours_conges_mois' => ['Congés acquis par mois travaillé (jours)', 'decimal', 2.2, 'RH & paie'],
        'rh.prefixe_matricule' => ['Préfixe des matricules', 'texte', 'EMP', 'RH & paie'],
        'paie.jour_paiement' => ['Jour de paiement des salaires', 'entier', 28, 'RH & paie'],
        'paie.arrondi' => ['Arrondi du net à payer (0 = aucun)', 'entier', 5, 'RH & paie'],
        'paie.majoration_heures_sup' => ['Majoration des heures supplémentaires (%)', 'pourcentage', 15, 'RH & paie'],

        'compta.ecritures_auto' => ['Générer les écritures comptables automatiquement', 'booleen', true, 'Comptabilité'],
        'compta.debut_exercice_mois' => ['Mois de début de l\'exercice comptable', 'entier', 1, 'Comptabilité'],

        'analyse.seuil_risque_jours' => ['Retard à partir duquel un client est « à risque » (jours)', 'entier', 60, 'Analyse'],
        'analyse.objectif_recouvrement' => ['Objectif de taux de recouvrement (%)', 'pourcentage', 90, 'Analyse'],
        'analyse.objectif_occupation' => ['Objectif de taux d\'occupation locative (%)', 'pourcentage', 95, 'Analyse'],
        'analyse.ratio_masse_salariale' => ['Masse salariale maximale (% des encaissements)', 'pourcentage', 30, 'Analyse'],

        // canaux et textes de chaque notification : Paramétrage › Intégrations & messages
        'notification.rappel_jours' => ['Rappel avant échéance de vente ou de loyer (jours, 0 = aucun)', 'entier', 3, 'Notifications'],

        'vitrine.titre' => ['Titre du site', 'texte', '', 'Site vitrine'],
        'vitrine.slogan' => ['Slogan', 'texte', 'Trouvez le bien qui vous ressemble', 'Site vitrine'],
        'vitrine.a_propos' => ['Texte « À propos »', 'texte', '', 'Site vitrine'],

        'numerotation.bien' => ['Préfixe des biens', 'texte', 'B', 'Numérotation'],
        'numerotation.tiers' => ['Préfixe des tiers', 'texte', 'T', 'Numérotation'],
        'numerotation.reservation' => ['Préfixe des réservations', 'texte', 'RES', 'Numérotation'],
        'numerotation.vente' => ['Préfixe des contrats de vente', 'texte', 'CV', 'Numérotation'],
        'numerotation.mandat' => ['Préfixe des mandats', 'texte', 'MG', 'Numérotation'],
        'numerotation.bail' => ['Préfixe des baux', 'texte', 'BL', 'Numérotation'],
        'numerotation.quittance' => ['Préfixe des quittances', 'texte', 'QT', 'Numérotation'],
        'numerotation.decompte' => ['Préfixe des décomptes de sortie', 'texte', 'DS', 'Numérotation'],
        'numerotation.ged' => ['Préfixe des documents (GED)', 'texte', 'GED', 'Numérotation'],
        'numerotation.validation' => ['Préfixe des demandes de validation', 'texte', 'VAL', 'Numérotation'],
        'numerotation.reversement' => ['Préfixe des reversements', 'texte', 'RV', 'Numérotation'],
        'numerotation.appel' => ['Préfixe des appels de fonds', 'texte', 'AF', 'Numérotation'],
        'numerotation.encaissement' => ['Préfixe des reçus', 'texte', 'REC', 'Numérotation'],
        'numerotation.depense' => ['Préfixe des dépenses', 'texte', 'DEP', 'Numérotation'],
        'numerotation.sejour' => ['Préfixe des séjours', 'texte', 'SEJ', 'Numérotation'],
        'numerotation.intervention' => ['Préfixe des interventions', 'texte', 'INT', 'Numérotation'],
        'numerotation.avance' => ['Préfixe des avances au personnel', 'texte', 'AV', 'Numérotation'],
        'numerotation.bulletin' => ['Préfixe des bulletins de paie', 'texte', 'BP', 'Numérotation'],
        'numerotation.paiement_salaire' => ['Préfixe des paiements de salaire', 'texte', 'PS', 'Numérotation'],
    ],

    /*
    | Listes de valeurs : categorie => [libellé, [code => libellé]]
    */
    'referentiels' => [
        'type_bien' => ['Types de bien', [
            'TERRAIN' => 'Terrain', 'VILLA' => 'Villa', 'MAISON' => 'Maison basse', 'DUPLEX' => 'Duplex', 'APPARTEMENT' => 'Appartement',
            'STUDIO' => 'Studio', 'CHAMBRE' => 'Chambre', 'BUREAU' => 'Bureau', 'MAGASIN' => 'Magasin / boutique', 'ENTREPOT' => 'Entrepôt',
            'PARKING' => 'Parking', 'IMMEUBLE' => 'Immeuble entier',
        ]],
        'equipement' => ['Équipements', [
            'PISCINE' => 'Piscine', 'GARAGE' => 'Garage', 'JARDIN' => 'Jardin', 'CLIMATISATION' => 'Climatisation', 'GROUPE' => 'Groupe électrogène',
            'ASCENSEUR' => 'Ascenseur', 'GARDIENNAGE' => 'Gardiennage', 'FORAGE' => 'Forage', 'INTERNET' => 'Internet', 'CUISINE_EQUIPEE' => 'Cuisine équipée',
        ]],
        'type_titre_foncier' => ['Types de titre foncier', [
            'ACD' => 'Arrêté de concession définitive (ACD)', 'CPF' => 'Certificat de propriété foncière', 'TF' => 'Titre foncier',
            'LA' => 'Lettre d\'attribution', 'AV' => 'Attestation villageoise',
        ]],
        'type_piece_identite' => ['Types de pièce d\'identité', [
            'CNI' => 'Carte nationale d\'identité', 'PASSEPORT' => 'Passeport', 'ATTESTATION' => 'Attestation d\'identité',
            'CARTE_CONSULAIRE' => 'Carte consulaire', 'PERMIS' => 'Permis de conduire', 'RCCM' => 'Registre de commerce (RCCM)',
        ]],
        'situation_matrimoniale' => ['Situations matrimoniales', [
            'CELIBATAIRE' => 'Célibataire', 'MARIE' => 'Marié(e)', 'DIVORCE' => 'Divorcé(e)', 'VEUF' => 'Veuf / veuve', 'CONCUBINAGE' => 'Concubinage',
        ]],
        'mode_paiement' => ['Modes de paiement', [
            'ESPECES' => 'Espèces', 'CHEQUE' => 'Chèque', 'VIREMENT' => 'Virement', 'MOBILE_MONEY' => 'Mobile money', 'CARTE' => 'Carte bancaire',
        ]],
        'canal_acquisition' => ['Canaux d\'acquisition', [
            'SITE_WEB' => 'Site web', 'RESEAUX_SOCIAUX' => 'Réseaux sociaux', 'BOUCHE_A_OREILLE' => 'Bouche à oreille', 'PANNEAU' => 'Panneau / affichage',
            'SALON' => 'Salon / foire', 'RADIO_TV' => 'Radio / TV', 'PARTENAIRE' => 'Apporteur / partenaire', 'AUTRE' => 'Autre',
        ]],
        'motif_desistement' => ['Motifs de désistement', [
            'FINANCIER' => 'Difficultés financières', 'CHANGEMENT_PROJET' => 'Changement de projet', 'RETARD' => 'Retard de livraison', 'AUTRE' => 'Autre',
        ]],
        'categorie_document' => ['Catégories de documents (GED)', [
            'CONTRAT' => 'Contrat', 'QUITTANCE' => 'Quittance', 'RECU' => 'Reçu de paiement', 'ETAT_LIEUX' => 'État des lieux', 'DECOMPTE' => 'Décompte de sortie',
            'JUSTIFICATIF' => 'Justificatif de paiement', 'FACTURE_CIE' => 'Facture / reçu CIE', 'FACTURE_SODECI' => 'Facture SODECI', 'IDENTITE' => 'Pièce d\'identité',
            'PHOTO' => 'Photo', 'PLAN' => 'Plan', 'TITRE_FONCIER' => 'Titre foncier / ACD', 'COURRIER' => 'Courrier', 'BULLETIN' => 'Bulletin de paie',
            'COMPTABILITE' => 'Pièce comptable', 'FACTURE' => 'Facture fournisseur', 'PV' => 'Procès-verbal', 'AUTRE' => 'Autre',
        ]],
        'motif_resiliation' => ['Motifs de résiliation de bail', [
            'CONGE_LOCATAIRE' => 'Congé du locataire', 'CONGE_BAILLEUR' => 'Congé du bailleur', 'IMPAYES' => 'Impayés', 'FIN_BAIL' => 'Fin de bail', 'AUTRE' => 'Autre',
        ]],
        'type_reclamation' => ['Types de réclamation', [
            'TECHNIQUE' => 'Technique', 'ADMINISTRATIF' => 'Administratif', 'FINANCIER' => 'Financier', 'VOISINAGE' => 'Voisinage', 'AUTRE' => 'Autre',
        ]],
        'categorie_depense' => ['Catégories de dépense / postes de charges', [
            'ENTRETIEN' => 'Entretien et nettoyage', 'EAU' => 'Eau', 'ELECTRICITE' => 'Électricité', 'GARDIENNAGE' => 'Gardiennage / sécurité',
            'ASSURANCE' => 'Assurance', 'ASCENSEUR' => 'Ascenseur', 'HONORAIRES' => 'Honoraires du syndic', 'TRAVAUX' => 'Travaux', 'FOURNITURES' => 'Fournitures',
            'TAXES' => 'Impôts et taxes', 'AUTRE' => 'Autre',
        ]],
        'objet_rdv' => ['Objets de rendez-vous', [
            'VISITE' => 'Visite de bien', 'PRESENTATION' => 'Présentation de programme', 'SIGNATURE' => 'Signature', 'ETAT_LIEUX' => 'État des lieux',
            'REMISE_CLES' => 'Remise des clés', 'AUTRE' => 'Autre',
        ]],
        'categorie_publication' => ['Catégories de publication', [
            'ACTUALITE' => 'Actualité', 'CONSEIL' => 'Conseil', 'PROGRAMME' => 'Nouveau programme',
        ]],
        'departement' => ['Départements (RH)', [
            'DIRECTION' => 'Direction', 'COMMERCIAL' => 'Commercial', 'GESTION_LOCATIVE' => 'Gestion locative', 'SYNDIC' => 'Syndic',
            'FINANCES' => 'Finances & comptabilité', 'TECHNIQUE' => 'Technique', 'ADMINISTRATION' => 'Administration',
        ]],
        'poste' => ['Postes (RH)', [
            'DIRECTEUR' => 'Directeur', 'NEGOCIATEUR' => 'Négociateur / commercial', 'GESTIONNAIRE' => 'Gestionnaire locatif', 'SYNDIC' => 'Gestionnaire de copropriété',
            'COMPTABLE' => 'Comptable', 'CAISSIER' => 'Caissier', 'RECOUVREMENT' => 'Chargé de recouvrement', 'ASSISTANT' => 'Assistant(e)',
            'TECHNICIEN' => 'Technicien', 'GARDIEN' => 'Gardien', 'CHAUFFEUR' => 'Chauffeur',
        ]],
        'type_absence' => ['Types de congé et d\'absence', [
            'CONGE_ANNUEL' => 'Congé annuel', 'MALADIE' => 'Maladie', 'MATERNITE' => 'Maternité', 'PATERNITE' => 'Paternité',
            'EVENEMENT_FAMILIAL' => 'Événement familial', 'SANS_SOLDE' => 'Congé sans solde', 'ABSENCE_INJUSTIFIEE' => 'Absence injustifiée', 'FORMATION' => 'Formation',
        ]],
        'corps_metier' => ['Corps de métier (fournisseurs)', [
            'PLOMBERIE' => 'Plomberie', 'ELECTRICITE' => 'Électricité', 'MACONNERIE' => 'Maçonnerie', 'PEINTURE' => 'Peinture', 'MENUISERIE' => 'Menuiserie',
            'CLIMATISATION' => 'Climatisation', 'NETTOYAGE' => 'Nettoyage', 'SECURITE' => 'Sécurité', 'AUTRE' => 'Autre',
        ]],
    ],

    /*
    | Frais annexes par défaut : [code, libellé, activité (vente|location|syndic|meuble), montant, obligatoire, document requis]
    */
    'frais' => [
        ['OUVERTURE', 'Frais d\'ouverture de dossier', 'vente', 200000, true, false],
        ['DOSSIER_TECHNIQUE', 'Frais de dossier technique', 'vente', 100000, false, false],
        ['ATTESTATION_COUTUMIERE', 'Attestation coutumière', 'vente', 500000, false, true],
        ['ACD', 'Frais d\'ACD', 'vente', 101000, false, true],
        ['HUISSIER', 'Frais d\'huissier de justice', 'vente', 80000, false, true],
        ['NOTAIRE', 'Émoluments notaire (suivi de l\'ACD)', 'vente', 105000, false, true],
        ['CONTRAT_BAIL', 'Frais de contrat de bail', 'location', 10000, true, false],
    ],

    // fichiers privés (pièces d'identité, contrats) / publics (photos publiées)
    'stockage' => [
        'prive' => 'local',
        'public' => 'public',
    ],
];
