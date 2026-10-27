<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nom de l'application (titre, mails, SMS)
    |--------------------------------------------------------------------------
    */
    'app_name' => env('RBAC_APP_NAME', env('APP_NAME', 'Application')),

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    | Les traces sont écrites dans une table placée dans un schéma à part
    | (PostgreSQL) : audit.log par défaut. Une connexion dédiée peut être
    | utilisée (ex. base d'audit séparée) via RBAC_AUDIT_CONNECTION.
    */
    'audit' => [
        'enabled' => env('RBAC_AUDIT_ENABLED', true),
        'connection' => env('RBAC_AUDIT_CONNECTION'), // null = connexion par défaut
        'schema' => env('RBAC_AUDIT_SCHEMA', 'audit'),
        'table' => env('RBAC_AUDIT_TABLE', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    */
    // file d'attente des e-mails / SMS / WhatsApp (canaux configurés dans Intégrations & messages)
    'notifications' => [
        'queue' => env('RBAC_NOTIFICATIONS_QUEUE', 'notifications'),
    ],

    'mail' => [
        'from_name' => env('RBAC_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Application')),
        'queue' => env('RBAC_MAIL_QUEUE', 'email-queue'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS (App\Helpers\Envoisms)
    |--------------------------------------------------------------------------
    | driver : "symtel" (passerelle utilisée par FDFP / CAPAVENIRJEUNESCI),
    |          "http"   (passerelle générique POST),
    |          "log"    (aucun envoi réel, écrit dans storage/logs - défaut).
    */
    'sms' => [
        'driver' => env('RBAC_SMS_DRIVER', 'log'),
        'queue' => env('RBAC_SMS_QUEUE', 'sms-queue'),
        'indicatif' => env('RBAC_SMS_INDICATIF', '225'),
        'sender' => env('RBAC_SMS_SENDER', 'FDFP'),
        'timeout' => 15,

        'symtel' => [
            'url' => env('RBAC_SMS_SYMTEL_URL', 'https://www.symtel.biz/fr/index.php?mod=cgibin&page=2'),
            'user' => env('RBAC_SMS_SYMTEL_USER'),
            'code' => env('RBAC_SMS_SYMTEL_CODE'),
        ],

        'http' => [
            'url' => env('RBAC_SMS_HTTP_URL'),
            'token' => env('RBAC_SMS_HTTP_TOKEN'),
            // noms des champs envoyés à la passerelle
            'fields' => ['phone' => 'to', 'message' => 'message', 'sender' => 'from'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chiffrement des identifiants dans les URL (App\Helpers\Crypt)
    |--------------------------------------------------------------------------
    */
    'hashids' => [
        'salt' => env('HASHIDS_SALT', env('APP_KEY', 'rbac')),
        'length' => 10,
        'alphabet' => 'abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    ],

    /*
    |--------------------------------------------------------------------------
    | Compte super administrateur créé par le seeder
    |--------------------------------------------------------------------------
    */
    // sécurité des comptes : verrouillage temporaire après des échecs de connexion répétés
    'securite' => [
        'tentatives_max' => (int) env('RBAC_TENTATIVES_MAX', 5),
        'verrouillage_minutes' => (int) env('RBAC_VERROUILLAGE_MINUTES', 15),
    ],

    'super_admin' => [
        'login' => env('RBAC_ADMIN_LOGIN', 'superadmin'),
        'email' => env('RBAC_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('RBAC_ADMIN_PASSWORD', 'Admin@2026!'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paramètres système (table logo) : types qui n'acceptent qu'une seule
    | valeur active à la fois. Les autres types (RESEAUX SOCIAUX,
    | IMAGE DASHBORD) peuvent en avoir plusieurs.
    */
    'parametres' => [
        'types' => [
            'LOGO' => 'LOGO',
            'EMAIL' => 'EMAIL',
            'CONTACT' => 'CONTACT',
            'RESEAUX SOCIAUX' => 'RESEAUX SOCIAUX',
            'HEURE OUVERTURE' => 'HEURE OUVERTURE',
            'LOCALISATION' => 'LOCALISATION',
            'CONTACT INFO' => 'CONTACT INFO',
            'MAP LATITUDE' => 'MAP LATITUDE',
            'MAP LONGUEUR' => 'MAP LONGUEUR',
            'NEWSLETTER' => 'NEWSLETTER',
            'COULEUR MENU HAUT' => 'COULEUR',
            'IMAGE ACCEUIL' => 'IMAGE ACCEUIL',
            'IMAGE DASHBORD' => 'IMAGE DASHBORD',
            'ESPACE CLIENT' => 'ESPACE CLIENT',
            'OUVRIR COMPTE' => 'OUVRIR COMPTE',
            'SITEVITRINE' => 'ACTIVE LE SITE VITRINE',
        ],
        'multiples' => ['RESEAUX SOCIAUX', 'IMAGE DASHBORD'],
        // types dont on ne peut pas désactiver la dernière valeur active
        'obligatoires' => ['COULEUR MENU HAUT'],
        'upload_path' => 'frontend/logo',
    ],

    // icône par défaut des sous-menus (menu latéral), par code
    'icones' => [
        'roles' => 'ti ti-users-group',
        'parametresysteme' => 'ti ti-palette',
        'menus' => 'ti ti-menu-2',
        'sousmenus' => 'ti ti-list-tree',
        'permissions' => 'ti ti-key',
        'menuprofil' => 'ti ti-lock-access',
        'audits' => 'ti ti-history',
        'users' => 'ti ti-user-cog',
        'programmes' => 'ti ti-building-community',
        'lotissements' => 'ti ti-map-2',
        'immeubles' => 'ti ti-building',
        'biens' => 'ti ti-home',
        'tiers' => 'ti ti-address-book',
        'prospects' => 'ti ti-user-search',
        'rendezvous' => 'ti ti-calendar-event',
        'reclamations' => 'ti ti-message-report',
        'demandescontact' => 'ti ti-mail-opened',
        'reservations' => 'ti ti-bookmark',
        'ventes' => 'ti ti-file-certificate',
        'echeances' => 'ti ti-calendar-due',
        'mandats' => 'ti ti-file-signature',
        'baux' => 'ti ti-key',
        'loyers' => 'ti ti-receipt-2',
        'reversements' => 'ti ti-arrows-exchange',
        'coproprietes' => 'ti ti-buildings',
        'appelsfonds' => 'ti ti-file-invoice',
        'assemblees' => 'ti ti-users-group',
        'interventions' => 'ti ti-tool',
        'sejours' => 'ti ti-bed',
        'encaissements' => 'ti ti-cash',
        'depenses' => 'ti ti-receipt-off',
        'caisse' => 'ti ti-cash-register',
        'campagnes' => 'ti ti-speakerphone',
        'publications' => 'ti ti-news',
        'slides' => 'ti ti-slideshow',
        'temoignages' => 'ti ti-quote',
        'etats' => 'ti ti-report',
        'objectifs' => 'ti ti-target-arrow',
        'recouvrements' => 'ti ti-coin',
        'reglesrelance' => 'ti ti-bell-ringing',
        'employes' => 'ti ti-id-badge-2',
        'absences' => 'ti ti-beach',
        'presences' => 'ti ti-clock-check',
        'avances' => 'ti ti-cash-banknote',
        'paie' => 'ti ti-wallet',
        'rubriques' => 'ti ti-list-numbers',
        'ecritures' => 'ti ti-notebook',
        'comptabilite' => 'ti ti-calculator',
        'plancomptable' => 'ti ti-list-details',
        'analyse' => 'ti ti-chart-bar',
        'simulateur' => 'ti ti-calculator',
        'ged' => 'ti ti-folders',
        'moncompte' => 'ti ti-user-circle',
        'agences' => 'ti ti-building-community',
        'monagence' => 'ti ti-building-store',
        'parametres' => 'ti ti-settings',
        'referentiels' => 'ti ti-list-check',
        'fraistypes' => 'ti ti-receipt-tax',
        'integrations' => 'ti ti-plug-connected',
        'validations' => 'ti ti-checklist',
        'circuits' => 'ti ti-route',
        'siteweb' => 'ti ti-world-www',
        'plateforme' => 'ti ti-dashboard', 'plateforme/sauvegardes' => 'ti ti-database-export', 'plateforme/securite' => 'ti ti-shield-check',
        'configuration/modules' => 'ti ti-puzzle',
    ],

    // écrans d'administration proposés au super administrateur hors agence (en plus du menu PLATEFORME)
    'hors_agence' => ['users', 'roles', 'menuprofil', 'audits', 'integrations'],
];
