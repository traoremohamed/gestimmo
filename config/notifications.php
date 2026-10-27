<?php

/*
|--------------------------------------------------------------------------
| Modèles de messages par défaut (copiés pour chaque agence, modifiables à l'écran)
|--------------------------------------------------------------------------
| code => [libellé, canaux, sujet, corps e-mail, corps SMS / WhatsApp, variables]
| Variables entre accolades ; {agence} est toujours disponible.
*/

return [
    'COMPTE_CLIENT' => ['Ouverture de l\'espace client', ['EMAIL', 'SMS'], 'Votre espace client {agence}',
        "Votre espace client {agence} est ouvert : vous y retrouvez vos contrats, sommes dues, reçus et quittances.\n\nIdentifiant : {identifiant}\nMot de passe provisoire : {mot_de_passe}\n\nConnexion : {lien}",
        '{agence} : votre espace client est ouvert. Identifiant {identifiant}, mot de passe provisoire {mot_de_passe}. {lien}',
        '{nom}, {identifiant}, {mot_de_passe}, {lien}'],
    'COMPTE_UTILISATEUR' => ['Création d\'un compte utilisateur', ['EMAIL', 'SMS'], 'Création de votre compte {agence}',
        "Votre compte a été créé.\n\nIdentifiant : {identifiant}\nMot de passe : {mot_de_passe}\n\nVous devrez le changer à la première connexion : {lien}",
        '{agence} - Identifiant : {identifiant} Mot de passe : {mot_de_passe} Accès : {lien}',
        '{nom}, {identifiant}, {mot_de_passe}, {lien}'],
    'RECU_PAIEMENT' => ['Reçu de paiement', ['EMAIL', 'SMS'], 'Reçu {numero} — {agence}',
        "Nous accusons réception de votre paiement de {montant} du {date} ({mode}).\n\nRéférence du reçu : {numero}\n{details}\n\nVotre reçu signé électroniquement (QR code) est joint à ce message.\n\nMerci de votre confiance.",
        '{agence} : paiement de {montant} reçu le {date}, reçu {numero}. Merci.',
        '{nom}, {numero}, {montant}, {date}, {mode}, {details}'],
    'QUITTANCE_LOYER' => ['Quittance de loyer', ['EMAIL'], 'Quittance de loyer {periode}',
        "Votre loyer de {periode} pour {bien} est entièrement réglé ({montant}).\n\nQuittance n° {numero}, disponible dans votre espace client.",
        '{agence} : loyer {periode} réglé ({montant}). Quittance {numero} disponible dans votre espace client.',
        '{nom}, {periode}, {bien}, {montant}, {numero}'],
    'RAPPEL_ECHEANCE' => ['Rappel avant échéance', ['SMS'], 'Rappel : échéance du {date}',
        "Pour rappel, l'échéance « {libelle} » de {montant} arrive le {date}.\n\nSi vous avez déjà réglé, merci de ne pas tenir compte de ce message.",
        '{agence} : rappel, {libelle} de {montant} à régler le {date}. Merci.',
        '{nom}, {libelle}, {montant}, {date}'],
    'RAPPEL_RDV' => ['Rappel de rendez-vous', ['SMS'], 'Rappel de votre rendez-vous du {date}',
        "Nous vous rappelons votre rendez-vous « {objet} » le {date} à {heure}{lieu}.",
        '{agence} : rappel de votre rendez-vous le {date} à {heure}{lieu}. {objet}',
        '{nom}, {objet}, {date}, {heure}, {lieu}'],
    'APPEL_FONDS' => ['Appel de fonds de copropriété', ['EMAIL'], 'Appel de fonds : {libelle}',
        "La copropriété {copropriete} procède à l'appel de fonds « {libelle} ».\n\nVotre quote-part (lot {lot}) : {montant}, à régler avant le {date}.",
        '{agence} : appel de fonds {copropriete}, lot {lot} : {montant} avant le {date}.',
        '{nom}, {copropriete}, {libelle}, {lot}, {montant}, {date}'],
    'DECOMPTE_SORTIE' => ['Décompte de sortie du locataire', ['EMAIL', 'SMS'], 'Décompte de sortie — bail {bail}',
        "Suite à votre départ, voici le décompte de vos garanties (n° {numero}) :

Garanties et sommes dues à vous : {garanties}
Retenues (impayés, réparations, factures) : {retenues}

Montant à vous restituer : {montant}
Reste dû par vous : {reste_du}

Le décompte détaillé, signé électroniquement, est disponible sur demande ou dans votre espace client.",
        '{agence} : décompte de sortie {numero}. A vous restituer : {montant}. Reste dû : {reste_du}.',
        '{nom}, {numero}, {bail}, {garanties}, {retenues}, {montant}, {reste_du}'],
    'RESTITUTION_GARANTIE' => ['Restitution des garanties', ['EMAIL', 'SMS'], 'Restitution de vos garanties',
        "Nous vous avons restitué {montant} le {date} au titre du décompte de sortie {numero}.

Merci de votre confiance.",
        '{agence} : {montant} vous ont été restitués le {date} (décompte {numero}).',
        '{nom}, {numero}, {montant}, {date}'],
    'VALIDATION_A_DECIDER' => ['Demande de validation à décider', ['EMAIL'], 'À valider : {resume}',
        "Une demande attend votre décision (étape « {etape} »).\n\n{resume}\nMontant : {montant}\nDemandée par : {demandeur}\n\nDécider : {lien}",
        '{agence} : demande {numero} à valider ({etape}). {lien}',
        '{nom}, {numero}, {resume}, {etape}, {montant}, {demandeur}, {lien}'],
    'VALIDATION_DECIDEE' => ['Décision sur une demande de validation', ['EMAIL'], 'Demande {numero} : {decision}',
        "Votre demande {numero} ({resume}) a été {decision}.\n\nDétail : {lien}",
        '{agence} : demande {numero} {decision}.',
        '{nom}, {numero}, {resume}, {decision}, {lien}'],
    'DEMANDE_CONTACT' => ['Accusé de réception d\'une demande (site)', ['EMAIL'], 'Nous avons bien reçu votre demande',
        "Merci pour votre message « {sujet} ». Un conseiller {agence} vous recontacte rapidement.",
        '{agence} : votre demande « {sujet} » est bien reçue. Un conseiller vous recontacte rapidement.',
        '{nom}, {sujet}'],
];
