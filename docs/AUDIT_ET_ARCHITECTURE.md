# Audit de l'application actuelle et architecture cible

> Application auditée : `immobilieramelioration` (Laravel 8, PHP 7.3, MySQL `baseimmobilier`).
> Cible : `immobilier_saas` (Laravel 13, PHP 8.3, PostgreSQL), plateforme SaaS multi-agences.
> Date : 24/10/2026.

---

## 1. Inventaire fonctionnel de l'existant

| Domaine | Fonctionnalités existantes | Tables principales |
|---|---|---|
| Patrimoine | Sites, lotissements, îlots, lots/biens, immeubles, génération en masse de lots et d'appartements, fichiers/plans, publication | `site`, `lotissement`, `lot`, `immeuble`, `bien_a_generer`, `bienagenererlocation`, `bien_location`, `fichier`, `image_loca` |
| Tiers / CRM | Clients, prospects, locataires, propriétaires (bailleurs), pièces d'identité, préférences, canaux de communication, prospection, rendez-vous (avec alertes), réclamations | `client` (≈70 colonnes), `prospection`, `rendez_vous`, `canalcom(client)`, `reclamation`, `piece_client`, `proprietaire_bien` |
| Vente | Intention d'achat (blocage de bien), montage de dossier, frais annexes (pièces), échéancier, encaissement acompte/échéances/paiements partiels, reçus, désistement, remboursement, transfert, attribution de bien, documents à notifier | `reservationcom`, `dossier`, `dossier_lot`, `dossier_piece`, `echancier`, `echancier_partielle`, `piece` |
| Location | Montage de dossier location, caution / avance / frais d'agence, paiement des loyers, liste des locataires, demandes de RDV pour bien en location | `dossier` (colonnes `loyer`, `mois_*`), `echancier`, `frais_annexes_location` |
| Résidences meublées | Réservations/commandes et factures de nuitées | `reservation_commande`, `reservation_facture`, lignes |
| Marketing | Campagnes e-mail (interne, externe, par préférence), newsletter, contacts | `campagne`, `campagne_contact`, `contact` |
| Site vitrine | Accueil, propriétés, agents, actualités, articles, slides, témoignages, contact, prise de RDV, captcha | `actualite`, `article`, `slide`, `temoignange`, `annonce*` |
| Pilotage | 15 états : biens, RDV, CA (vente/location), encaissements, impayés clients/locataires, documents, canaux, attribution d'objectifs commerciaux | vues SQL `vue_*` |
| Administration | Profils, modules/sous-modules, permissions, droits d'accès, utilisateurs, personnel, paramètres système | socle RBAC |
| Ébauche | Workflow de validation (processus, parcours, combinaison) jamais terminé (tables vides) | `processus*`, `parcours`, `demande` |

## 2. Constats de l'audit

### 2.1 Sécurité — critique

| # | Constat | Impact | Correction dans la cible |
|---|---|---|---|
| S1 | **Aucun contrôle d'autorisation** dans les contrôleurs (0 appel `can`/`authorize`) : seule l'authentification est vérifiée ; la visibilité repose sur le menu. | Tout utilisateur connecté (y compris un CLIENT ou un LOCATAIRE) peut appeler n'importe quelle URL : encaisser, désister, rembourser. | Middleware `can:` sur chaque route + vérification dans les services. |
| S2 | **IDOR** : les identifiants en URL (même « cryptés » par Hashids) ne sont pas rapprochés de l'utilisateur ; l'espace client lit les dossiers d'autrui en changeant l'ID. | Fuite de données personnelles et financières. | Scope `agence_id` global + politiques « propriétaire de la ressource » pour les espaces clients. |
| S3 | **Montants fournis par le navigateur** : `paiementlocataire` enregistre le montant envoyé dans le formulaire (`route[] = "montant/date"`). | Un utilisateur peut solder un loyer avec un montant arbitraire. | Les montants sont toujours recalculés côté serveur à partir de l'échéance. |
| S4 | Téléversements sans contrôle de type ni de taille (`move()` dans `public/`, 2 règles `mimes` sur tout le projet). | Dépôt d'un script exécutable, documents d'identité accessibles publiquement. | Stockage privé (`storage/app/private`), règles `mimes`/`max`, téléchargement via contrôleur autorisé. |
| S5 | 269 sorties non échappées `{!! !!}`, HTML construit par concaténation dans les contrôleurs (`<option>`…). | XSS stockée (noms clients, commentaires, actualités). | Blade `{{ }}` partout, composants Blade pour les `<select>`. |
| S6 | `.env` : `APP_DEBUG=true`, base MySQL `root` sans mot de passe ; identifiants SMTP/SMS en clair dans le dépôt. | Exposition des secrets et des traces d'erreur. | `.env` hors dépôt, `APP_DEBUG=false` en production. |
| S7 | Routes `GET` qui modifient des données (`/activeclient/{id}`, `/dossiermontagesuppri/...`, `/libererbien/{id}`). | CSRF, suppressions déclenchées par un simple lien. | Actions d'écriture en `POST/PUT/DELETE` uniquement. |
| S8 | PHP 7.3 et Laravel 8 hors support (plus de correctifs de sécurité). | Vulnérabilités connues non corrigées. | Laravel 13 / PHP 8.3. |

### 2.2 Intégrité des données — critique

| # | Constat | Correction |
|---|---|---|
| D1 | **Aucune transaction** (0 `DB::transaction`) alors qu'un montage de dossier écrit dans 4 tables. Une erreur à mi-parcours laisse un dossier validé sans échéancier, ou un lot marqué payé sans dossier. | Services métier transactionnels. |
| D2 | **Aucune clé étrangère** en base ; aucune migration du schéma métier (il n'existe que dans la base de production). | Migrations versionnées avec contraintes `foreign`, `unique`, index. |
| D3 | Montants stockés en `varchar` ou `double` (`montant_total_bien varchar(11)`, `loyer varchar(120)`, `montant_echeance double`). Erreurs d'arrondi, tri impossible, `varchar(11)` tronque les montants ≥ 100 milliards. | `decimal(15,2)` partout + arrondi maîtrisé de la dernière échéance. |
| D4 | États codés par une vingtaine de booléens contradictoires (`flag_payer_lot`, `flag_solder_lot`, `flag_bloquer_lot`, `flag_en_cours_location`, `flag_resisdence`…) sans machine d'états. | Un champ `statut` (enum PHP) par entité + historique des transitions. |
| D5 | Une table `client` de ≈70 colonnes mélange prospect, acquéreur, locataire, propriétaire, conjoint, employeur et 3 pièces d'identité dupliquées en colonnes. | Table `tiers` + rôles multiples, tables `tiers_pieces`, `tiers_contacts`. |
| D6 | Paramètres métier en dur : acompte « 50 % », « 6 mois », frais en `piece`, `id_piece = 3`, `type_cli = 1`. | Paramètres par agence et référentiels éditables. |
| D7 | Tables et fichiers « old » / copies (`Clientold.php`, `TableaudebordController19112020.php`, `slideold`, `migrationsCopie/`). | Supprimés ; historique conservé dans git. |

### 2.3 Qualité du code et maintenabilité

- Contrôleurs géants : `DossierController` 3 149 lignes, `ClientController` 1 768, `TableaudebordController` 1 356 ; logique métier, requêtes SQL et HTML mélangés.
- Le chargement du menu (5 jointures) est recopié en tête de nombreuses actions au lieu d'être dans le layout.
- Actions multiplexées par `switch ($request->input('action'))` sur le libellé du bouton (« Generé les échéances »).
- Aucun test automatisé du métier ; 2 `dd()` restants.
- Validation présente sur 40 actions seulement.
- Requêtes N+1 dans les listes, `->get()` sans pagination sur les listes de lots et clients.

### 2.4 Couverture fonctionnelle manquante

- **Syndic de copropriété** : inexistant (tantièmes, budgets, appels de fonds, AG, travaux).
- **Gestion locative** incomplète : pas de bail structuré, pas d'avis d'échéance/quittance, pas de reversement aux propriétaires, pas de commission de gestion, pas d'état des lieux, pas de révision de loyer, pas de relances.
- **Vente** : pas de promesse/compromis, pas de commission d'agence, pas de suivi notaire/ACD paramétrable, échéancier uniquement linéaire mensuel.
- **Caisse/finances** : pas de caisse unique, pas de dépenses, pas de rapprochement, reçus non numérotés de façon séquentielle.
- **Multi-agences** : impossible ; tout est global.
- Aucun module activable/désactivable.

## 3. Architecture cible

### 3.1 Principes

1. **Multi-agences (SaaS)** : base unique, chaque table métier porte `agence_id`. Le trait `App\Models\Concerns\AppartientAAgence` ajoute un scope global et renseigne automatiquement `agence_id`. Les utilisateurs de la plateforme (super administrateur) n'ont pas d'agence et peuvent changer d'agence active.
2. **Modulaire** : chaque domaine est un module (`config/immobilier.php`) activable par agence (`agence_modules`). Middleware `module:<code>` sur les routes ; le menu n'affiche que les modules actifs.
3. **Paramétrable** :
   - `parametres` (clé/valeur typée par agence, avec valeurs par défaut dans la config) : taux d'acompte, durée de réservation, jour d'échéance des loyers, pénalités, préfixes de numérotation, etc. ;
   - `referentiels` (listes de valeurs par agence : types de bien, types de pièce d'identité, modes de paiement, canaux, situations matrimoniales, motifs de désistement, types de charges…) ;
   - `frais_types` (frais annexes paramétrables par activité : vente, location, syndic) ;
   - `sequences` (numérotation des contrats, reçus, quittances, appels de fonds par agence et par année).
4. **Logique métier dans des services** transactionnels (`app/Services/*`), contrôleurs minces, `FormRequest` pour la validation, enums PHP pour les statuts.
5. **Sécurité** : permissions spatie sur chaque route, fichiers privés, montants recalculés côté serveur, routes d'écriture en POST/PUT/DELETE, audit systématique (socle `audit.log`).
6. **Interface** : layout Bootstrap 5 du socle, composants Blade (`x-page`, `x-form.*`, `x-statut`), menu construit depuis les modules actifs.

### 3.2 Modules

| Code | Module | Contenu |
|---|---|---|
| `patrimoine` | Patrimoine | Programmes/sites, lotissements, immeubles, biens (terrain, villa, appartement, bureau, local, parking…), caractéristiques, médias, génération en masse |
| `tiers` | Tiers & CRM | Tiers (personne physique/morale) aux rôles multiples : prospect, acquéreur, locataire, propriétaire, copropriétaire, fournisseur ; pièces ; prospects ; rendez-vous ; réclamations |
| `vente` | Vente | Réservations (blocage avec expiration), contrats de vente, frais annexes, échéancier paramétrable, désistement, remboursement, transfert |
| `location` | Gestion locative | Mandats de gestion (propriétaire, commission), baux, garanties, génération des loyers, quittances, impayés, résiliation, reversements propriétaires |
| `meuble` | Résidences meublées | Réservations à la nuitée, factures |
| `syndic` | Syndic de copropriété | Copropriétés, lots et tantièmes, budgets, appels de fonds, assemblées générales, résolutions, interventions/travaux |
| `finance` | Caisse & finances | Encaissements uniques affectés (échéances, loyers, appels de fonds, frais), reçus numérotés, dépenses |
| `marketing` | Marketing | Campagnes e-mail/SMS, contacts newsletter |
| `vitrine` | Site vitrine | Site public par agence : annonces, actualités, slides, témoignages, contact, demande de visite |
| `recouvrement` | Recouvrement & relances | Dossiers par débiteur et activité (ventes, loyers, charges, séjours), relances graduées automatiques (SMS, e-mail, courrier, mise en demeure), promesses de paiement, contentieux, alertes |
| `rh` | RH & personnel | Dossiers du personnel, contrats et avenants, carrière, présences, congés et absences avec validation, avances et prêts |
| `paie` | Paie | Périodes, éléments variables, bulletins (CNPS, CMU, ITS avec parts), commissions sur ventes, validation, paiement, livre de paie |
| `comptabilite` | Comptabilité | SYSCOHADA, écritures automatiques depuis les opérations (fonds de tiers en 4671/4672/165), saisie manuelle, contre-passation, balance, grand livre, bilan, résultat, clôture, export |
| `analyse` | Analyse & décision | Indicateurs comparés, prévisions d'encaissement, score de risque des débiteurs, rentabilité, recommandations, simulateur |
| `pilotage` | Tableaux de bord | Indicateurs et états par module (ventes, location, syndic, meublé, trésorerie, recouvrement, RH, paie) imprimables et CSV |
| `ged` | GED — Documents | Tout fichier rattaché aux dossiers, archivage automatique des documents émis, versions, empreinte SHA-256, signature QR vérifiable publiquement |
| `admin` | Administration (socle) | Agences, modules, paramètres, référentiels, utilisateurs, profils, droits, audit ; intégrations : canaux e-mail / SMS / WhatsApp par agence (repli plateforme), messages, gabarit, journal d'envoi |

### 3.3 Modèle de données (simplifié)

```
agences ─┬─ agence_modules, parametres, referentiels, sequences, users
         │
patrimoine: programmes ─ lotissements ─┐
            immeubles ──────────────────┼─ biens ─ medias (polymorphe)
                                        │
tiers ─┬─ tiers_roles, tiers_pieces     │
       │                                │
vente: reservations, ventes ─ vente_biens, vente_frais, echeances
location: mandats ─ baux ─ loyers (avis d'échéance)
syndic: coproprietes ─ copro_lots (tantièmes) ─ budgets ─ appels_fonds ─ appel_lignes
        assemblees ─ resolutions
finance: encaissements ─ encaissement_affectations (polymorphe : echeance, loyer, appel_ligne, frais)
         depenses
crm: prospects, rendez_vous, reclamations
```

### 3.4 Correspondance ancien → nouveau

| Ancien | Nouveau |
|---|---|
| `site` | `programmes` |
| `lotissement`, `ilot` (colonne) | `lotissements` (+ `ilot` sur le bien) |
| `lot`, `bien_location`, `bien_a_generer`, `bienagenererlocation` | `biens` (+ service de génération) |
| `immeuble` | `immeubles` |
| `client`, `personnel` (partie tiers), `employeur_client`, `piece_client` | `tiers`, `tiers_roles`, `tiers_pieces` |
| `reservationcom` | `reservations` |
| `dossier` (vente), `dossier_lot`, `dossier_piece` | `ventes`, `vente_biens`, `vente_frais` |
| `dossier` (location) | `baux` |
| `echancier` (vente) / (location) | `echeances` / `loyers` |
| `echancier_partielle` | `encaissements` + `encaissement_affectations` |
| `piece`, `type_reglement`, `type_bien`, `type_client`, `situation_matri`, `canalcom`, `pays` | `frais_types`, `referentiels`, `pays` |
| `reservation_commande`/`_facture` | `sejours` |
| `rendez_vous`, `prospection`, `reclamation`, `contact` | `rendez_vous`, `prospects`, `reclamations`, `demandes_contact` |
| `actualite`, `article`, `slide`, `temoignange` | `publications` (type), `slides`, `temoignages` |

La commande `php artisan immobilier:import-legacy` réalise cette reprise.

## 4. État d'avancement (25/10/2026)

| Étape | État |
|---|---|
| Fondations : agences, modules, paramètres, listes de valeurs, numérotation, isolation par agence | Fait |
| Patrimoine, Tiers & CRM, Ventes, Caisse, Gestion locative, Syndic, Interventions, Meublé, Marketing, Vitrine, Pilotage, Espace client | Fait (services, écrans, documents PDF) |
| Reprise des données (`immobilier:import-legacy`) | Fait — totaux encaissés rapprochés avec l'ancienne base |
| Tâches planifiées (réservations, loyers, pénalités, recouvrement, relances, congés) | Fait |
| Recouvrement & relances, alertes, RH, paie, comptabilité, analyse & décision, états par module | Fait |
| Aide contextuelle sur chaque écran et guide par module (`/aide`) | Fait |
| Canaux e-mail / SMS / WhatsApp paramétrables à l'écran (comme le SIH), notifications d'événements, journal d'envoi | Fait |
| Décompte de sortie (garanties, cautions SODECI / CIE, crédit compteur CIE, réparations), GED, signature QR, exports Excel / PDF | Fait |
| Tests automatisés | 61 tests, 483 assertions (métier, paie, comptabilité, écrans, sécurité) — base dédiée `db_immobilier_test` |

Corrections d'audit vérifiées par les tests : permission sur chaque route (S1), isolation par agence y compris
sur les identifiants d'URL (S2), montants recalculés côté serveur (S3), fichiers privés (S4), échappement Blade (S5),
écritures en POST/PUT/DELETE (S7), transactions et contraintes (D1, D2), montants en decimal (D3), statuts typés (D4).

Reste à faire / à décider :

- Recopier les fichiers de l'ancienne application (photos, plans, pièces scannées) : l'import ne reprend que les données.
- Baux actifs repris : les loyers impayés générés depuis 2023 sont confirmés comme dettes à recouvrer ; ils sont suivis
  dans le module Recouvrement (9 dossiers ouverts sur l'agence reprise au 25/10/2026). Les relances SMS/e-mail
  partent réellement dès que les files d'attente tournent : vérifier coordonnées et messages avant d'activer le cron.
- Comptabilité de l'agence reprise générée a posteriori (244 encaissements, balance équilibrée) : à faire valider par le comptable.
- Paie : taux et barème ITS par défaut (Côte d'Ivoire) à faire confirmer par le cabinet social avant la première paie réelle.
- Profils de l'ancienne application rattachés aux nouveaux profils (`ImporterAncienneBase::PROFILS`) : à vérifier.
- Pistes : déclarations sociales et fiscales (CNPS, DISA, ITS), rapprochement bancaire, paiement en ligne (mobile money), signature électronique,
  profils propres à chaque agence (spatie « teams »), recherche AJAX dans les listes de tiers pour les gros volumes.

## 5. Plan de migration

1. Fondations : agences, modules, paramètres, référentiels, séquences, layout et menu par module.
2. Patrimoine et Tiers.
3. Vente et Finance (encaissements, reçus).
4. Gestion locative.
5. Syndic de copropriété.
6. Résidences meublées, Marketing, Vitrine.
7. Pilotage (tableaux de bord).
8. Import des données existantes, tests, recette.
