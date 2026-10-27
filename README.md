# Plateforme immobilière SaaS

Gestion immobilière multi-agences (Laravel 13, PHP 8.3, PostgreSQL) : patrimoine, tiers & CRM, ventes,
gestion locative, syndic de copropriété, résidences meublées, caisse, recouvrement & relances, RH & personnel, paie,
comptabilité SYSCOHADA, analyse & décision (BI), marketing, site vitrine, tableaux de bord et espace client.
Chaque écran a son aide contextuelle (bouton « Aide ») et un guide par module est disponible sur `/aide` (contenu : `config/aide.php`). Chaque module est activable par agence ; taux, délais, numérotation et listes de valeurs
sont paramétrables depuis l'interface.

L'audit de l'ancienne application et l'architecture sont décrits dans [docs/AUDIT_ET_ARCHITECTURE.md](docs/AUDIT_ET_ARCHITECTURE.md).

## Installation

```bash
composer install
cp .env.example .env && php artisan key:generate   # renseigner DB_* (PostgreSQL)
php artisan migrate --seed                         # socle RBAC, modules, profils, pays, agence de démonstration
php artisan storage:link                           # photos et logos publics
```

Comptes créés par le seeder :

| Compte | Identifiant | Mot de passe | Rôle |
|---|---|---|---|
| Super administrateur (plateforme) | `RBAC_ADMIN_LOGIN` (défaut `superadmin`) | `RBAC_ADMIN_PASSWORD` (défaut `Admin@2026!`) | toutes les agences ; changement obligatoire à la 1re connexion |
| Directeur de l'agence démo | `directeur.demo` | `Demo@2026!` | agence `DEMO` |

Site vitrine de l'agence démo : `/site/demo`.

## Reprise des données de l'ancienne application

Renseigner `LEGACY_DB_*` dans `.env` (base MySQL `baseimmobilier`), puis :

```bash
php artisan immobilier:import-legacy --agence=LEGACY --raison-sociale="Nom de la société"
php artisan immobilier:import-legacy --agence=LEGACY --reinitialiser   # rejouer l'import (purge l'agence d'abord)
```

Les paiements historiques sont recréés en encaissements `LEG-…` affectés aux échéances : les totaux encaissés
sont identiques à l'ancienne base. Les utilisateurs se connectent avec leur e-mail comme identifiant (partie avant
le @) et leur ancien mot de passe, puis doivent le changer.

## Tâches planifiées

```bash
php artisan immobilier:taches-quotidiennes   # réservations expirées, loyers du mois, pénalités, dossiers de recouvrement,
                                             # promesses, relances automatiques, acquisition mensuelle des congés (le 1er)
```

Planifiée chaque jour à 2 h (`routes/console.php`) : ajouter `* * * * * php artisan schedule:run` au cron du serveur.
Les e-mails, SMS et WhatsApp passent en file d'attente (`QUEUE_CONNECTION`) : faire tourner
`php artisan queue:work --queue=notifications,default` sur le serveur (superviseur ou service Windows).

## Sortie du locataire, GED, signature QR et exports

- **Décompte de sortie** (bail résilié) : dépôt de garantie + cautions SODECI / CIE versées + crédit de compteur CIE remboursé
  (compteur pris à crédit), moins impayés, réparations de l'état des lieux de sortie et dernières factures non réglées,
  avec justificatifs en GED ; compensation automatique, restitution du solde avec preuve, ou reste dû suivi en recouvrement.
  Options : *Paramètres métier › Sortie du locataire*.
- **GED** (module `ged`) : tout format de fichier (sauf exécutables et scripts), rattaché aux dossiers, versions, empreinte SHA-256 ;
  les documents émis (reçus, quittances, contrats, décomptes, bulletins…) y sont archivés automatiquement.
- **Signature QR** sur chaque document émis et chaque export PDF : page publique `/verifier/{jeton}` (authentique / révoqué / inconnu,
  comparaison d'un fichier à l'original archivé).
- **Exports** Excel (.xlsx), PDF signé ou CSV sur les listes, états, balance, bilan, grand livre et lignes d'écritures.
- À chaque paiement, le reçu (et la quittance) signé QR est joint à l'e-mail envoyé au client.

## Envoi des e-mails, SMS et WhatsApp

*Paramétrage › Intégrations & messages* , sans toucher au `.env` ni redémarrer :

- **Canaux** : e-mail SMTP ; SMS Orange (CI), Twilio, Infobip, Symtel ou passerelle HTTP ; WhatsApp Cloud API (Meta) ou Twilio.
  Mots de passe et clés chiffrés (APP_KEY), mode *sandbox* (simulé, journalisé), bouton de test.
- **Par agence** : chaque agence peut avoir ses propres canaux ; à défaut, ceux de la plateforme (configurés par le super
  administrateur hors agence) s'appliquent ; sans aucun canal, le mailer et la passerelle SMS du `.env` restent utilisés.
- **Messages** : texte et canaux de chaque notification (ouverture de compte, reçu, quittance, rappel d'échéance,
  rappel de rendez-vous, appel de fonds, accusé de demande du site) ; gabarit d'e-mail aux couleurs de l'agence.
- **Journal d'envoi** : chaque message (envoyé, simulé, en échec avec la cause), destinataire masqué.
- Relances de recouvrement et campagnes marketing peuvent utiliser WhatsApp.

## Paramétrage

- **Plateforme** (super administrateur) : *Paramétrage › Agences* — créer une agence, choisir ses modules et son abonnement ;
  le sélecteur en haut de page permet de travailler dans une agence.
- **Agence** : *Mon agence & modules*, *Paramètres métier* (acompte, échéances, caution, commission, tantièmes,
  préfixes de numérotation…), *Listes de valeurs*, *Frais annexes*, profils et droits (socle RBAC).
- **Recouvrement** : niveaux de relance (délai, canal SMS/WhatsApp/e-mail/courrier, message) ; **Paie** : rubriques, barème ITS ;
  **Comptabilité** : plan comptable et règles d'imputation (`config/comptabilite.php` pour les valeurs par défaut).
- Comptabilité d'une agence existante ou reprise : *Écritures › Générer depuis les opérations* (rattrapage idempotent).
- Nouveaux modules / paramètres / listes : `config/immobilier.php`, puis `php artisan db:seed --class=ImmobilierSeeder`.

## Tests

```bash
php artisan test   # base PostgreSQL de test : db_immobilier_test (phpunit.xml), 
```

44 tests : calculs monétaires, isolation multi-agences, cycle de vente, gestion locative, syndic, séjours,
parcours de tous les écrans, contrôles d'accès, site vitrine, espace client.
