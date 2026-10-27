<?php

/*
|--------------------------------------------------------------------------
| Site vitrine : types de blocs (sections) d'une page
|--------------------------------------------------------------------------
| Repris de MARIEATTIEKESAAS, complété des blocs immobiliers de l'ancien site (diaporama des biens, recherche, derniers
| biens, programmes, agents, actualités, témoignages). Chaque type déclare les champs de base utilisés (titre, sous_titre,
| contenu, image, image_fond), ses options et la structure de ses éléments répétables. L'éditeur du back-office et
| l'affichage public sont générés à partir de cette définition : ajouter un type = une entrée ici + une vue
| vitrine/sections/<type>.
|
| Champ d'option / d'élément : [libellé, type (text, textarea, number, select, image, icone, switch), options]
*/

$boutons = ['bouton_libelle' => ['Texte du bouton', 'text'], 'bouton_lien' => ['Lien du bouton (#ancre, /page, https://…)', 'text']];
$destinations = ['TOUS' => 'Tous les biens publiés', 'VENTE' => 'À vendre', 'LOCATION' => 'À louer', 'MEUBLE' => 'Meublés'];

return [
    'types' => [
        'SLIDER' => [
            'libelle' => 'Diaporama', 'icone' => 'ti ti-slideshow', 'champs' => [],
            'options' => ['source' => ['Contenu', 'select', ['MANUEL' => 'Diapositives saisies ci-dessous', 'BIENS' => 'Biens en vedette (publiés, avec photo)', 'SLIDES' => 'Bannières de l\'agence (Site vitrine › Bannières)']],
                'destination' => ['Biens affichés (source « biens »)', 'select', $destinations],
                'nombre' => ['Nombre de biens', 'number'],
                'hauteur' => ['Hauteur', 'select', ['MOYENNE' => 'Moyenne', 'GRANDE' => 'Grande', 'PLEIN_ECRAN' => 'Plein écran']],
                'intervalle' => ['Défilement (secondes, 0 = manuel)', 'number']],
            'elements' => ['image' => ['Image', 'image'], 'titre' => ['Titre', 'text'], 'texte' => ['Texte', 'textarea']] + $boutons,
        ],
        'HERO' => [
            'libelle' => 'Bandeau d\'accueil', 'icone' => 'ti ti-layout-navbar', 'champs' => ['titre', 'sous_titre', 'contenu', 'image_fond'],
            'options' => $boutons + ['recherche' => ['Afficher la recherche de biens', 'switch'], 'hauteur' => ['Hauteur', 'select', ['MOYENNE' => 'Moyenne', 'GRANDE' => 'Grande', 'PLEIN_ECRAN' => 'Plein écran']]],
        ],
        'RECHERCHE' => [
            'libelle' => 'Recherche de biens', 'icone' => 'ti ti-search', 'champs' => ['titre', 'sous_titre'],
            'options' => ['budget' => ['Champ « budget maximum »', 'switch'], 'programme' => ['Champ « programme »', 'switch']],
        ],
        'BIENS' => [
            'libelle' => 'Biens (automatique)', 'icone' => 'ti ti-home', 'champs' => ['titre', 'sous_titre'],
            'options' => ['destination' => ['Biens affichés', 'select', $destinations], 'type_bien' => ['Type de bien (code, vide = tous)', 'text'],
                'nombre' => ['Nombre maximum', 'number'], 'affichage' => ['Présentation', 'select', ['GRILLE' => 'Grille', 'CARROUSEL' => 'Carrousel']],
                'bouton' => ['Bouton « Voir tous les biens »', 'switch']],
        ],
        'PROGRAMMES' => [
            'libelle' => 'Programmes immobiliers (automatique)', 'icone' => 'ti ti-building-estate', 'champs' => ['titre', 'sous_titre'],
            'options' => ['nombre' => ['Nombre maximum', 'number']],
        ],
        'TEXTE_IMAGE' => [
            'libelle' => 'Texte et image (à propos…)', 'icone' => 'ti ti-layout-sidebar', 'champs' => ['titre', 'sous_titre', 'contenu', 'image'],
            'options' => ['position_image' => ['Image', 'select', ['DROITE' => 'à droite', 'GAUCHE' => 'à gauche']]] + $boutons,
        ],
        'CARTES' => [
            'libelle' => 'Services / atouts (cartes)', 'icone' => 'ti ti-layout-cards', 'champs' => ['titre', 'sous_titre'],
            'options' => ['colonnes' => ['Colonnes', 'select', ['2' => '2', '3' => '3', '4' => '4']]],
            'elements' => ['icone' => ['Icône (ex. ti ti-key)', 'icone'], 'image' => ['Image (facultative)', 'image'], 'titre' => ['Titre', 'text'], 'texte' => ['Texte', 'textarea']],
        ],
        'CHIFFRES' => [
            'libelle' => 'Chiffres clés', 'icone' => 'ti ti-123', 'champs' => ['titre'],
            'options' => ['automatique' => ['Ajouter les chiffres de l\'agence (biens vendus, en location, programmes, clients)', 'switch']],
            'elements' => ['valeur' => ['Valeur (ex. 15 ans, 2 000+)', 'text'], 'libelle' => ['Libellé', 'text']],
        ],
        'EQUIPE' => [
            'libelle' => 'Nos agents / équipe', 'icone' => 'ti ti-users-group', 'champs' => ['titre', 'sous_titre'],
            'options' => ['automatique' => ['Afficher les commerciaux de l\'agence (nom, fonction, contact)', 'switch']],
            'elements' => ['image' => ['Photo', 'image'], 'nom' => ['Nom', 'text'], 'fonction' => ['Fonction', 'text'], 'telephone' => ['Téléphone', 'text'], 'email' => ['E-mail', 'text']],
        ],
        'TEMOIGNAGES' => [
            'libelle' => 'Témoignages', 'icone' => 'ti ti-message-star', 'champs' => ['titre', 'sous_titre'],
            'options' => ['automatique' => ['Afficher les témoignages enregistrés (Site vitrine › Témoignages)', 'switch']],
            'elements' => ['nom' => ['Nom', 'text'], 'texte' => ['Témoignage', 'textarea'], 'note' => ['Note (1 à 5)', 'number'], 'image' => ['Photo', 'image']],
        ],
        'ACTUALITES' => [
            'libelle' => 'Actualités (automatique)', 'icone' => 'ti ti-news', 'champs' => ['titre', 'sous_titre'],
            'options' => ['nombre' => ['Nombre maximum', 'number']],
        ],
        'PARTENAIRES' => [
            'libelle' => 'Partenaires (logos)', 'icone' => 'ti ti-heart-handshake', 'champs' => ['titre'],
            'elements' => ['image' => ['Logo', 'image'], 'nom' => ['Nom', 'text'], 'lien' => ['Site web', 'text']],
        ],
        'GALERIE' => [
            'libelle' => 'Galerie photos', 'icone' => 'ti ti-photo', 'champs' => ['titre', 'sous_titre'],
            'options' => ['colonnes' => ['Colonnes', 'select', ['3' => '3', '4' => '4', '6' => '6']]],
            'elements' => ['image' => ['Image', 'image'], 'legende' => ['Légende', 'text']],
        ],
        'CTA' => [
            'libelle' => 'Appel à l\'action', 'icone' => 'ti ti-speakerphone', 'champs' => ['titre', 'contenu', 'image_fond'], 'options' => $boutons,
        ],
        'FAQ' => [
            'libelle' => 'Questions fréquentes', 'icone' => 'ti ti-help-circle', 'champs' => ['titre', 'sous_titre'],
            'elements' => ['question' => ['Question', 'text'], 'reponse' => ['Réponse', 'textarea']],
        ],
        'HORAIRES' => ['libelle' => 'Horaires d\'ouverture', 'icone' => 'ti ti-clock', 'champs' => ['titre', 'contenu']],
        'CONTACT' => [
            'libelle' => 'Contact (formulaire et coordonnées)', 'icone' => 'ti ti-phone', 'champs' => ['titre', 'sous_titre', 'contenu'],
            'options' => ['formulaire' => ['Formulaire de contact', 'switch'], 'carte' => ['Afficher la carte', 'switch'], 'horaires' => ['Afficher les horaires', 'switch']],
        ],
        'CARTE' => ['libelle' => 'Plan d\'accès (carte)', 'icone' => 'ti ti-map-pin', 'champs' => ['titre']],
        'HTML' => ['libelle' => 'Contenu libre', 'icone' => 'ti ti-code', 'champs' => ['titre', 'contenu']],
    ],

    'polices' => ['Inter', 'Poppins', 'Montserrat', 'Playfair Display', 'Lora', 'Nunito', 'Raleway', 'Roboto', 'Public Sans'],

    'images' => ['disque' => 'public', 'dossier' => 'site', 'taille_max_ko' => 4096],

    // adresses de pages réservées aux pages système du site (catalogue, fiche, actualités, contact)
    'slugs_reserves' => ['biens', 'actualites', 'contact', 'p', 'visite'],
];
