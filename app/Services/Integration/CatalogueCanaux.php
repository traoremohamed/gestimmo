<?php

namespace App\Services\Integration;

/**
 * Canaux d'envoi configurables (repris du SIH) : fournisseurs et champs de chaque fournisseur.
 * L'écran de configuration est entièrement construit à partir de ce catalogue.
 *
 * champ => [libellé, type (text|password|number|select|url|email), secret?, aide, options|défaut]
 */
class CatalogueCanaux
{
    public const CANAUX = [
        'EMAIL' => ['libelle' => 'E-mail (SMTP)', 'icone' => 'ti-mail', 'fournisseurs' => [
            'SMTP' => ['libelle' => 'Serveur SMTP', 'champs' => [
                'host' => ['Serveur SMTP', 'text', false, 'ex. smtp.gmail.com, mail.votre-domaine.ci', 'smtp.example.com'],
                'chiffrement' => ['Chiffrement', 'select', false, null, ['ssl' => 'SSL (port 465)', 'tls' => 'TLS / STARTTLS (port 587)', '' => 'Aucun (port 25)']],
                'port' => ['Port', 'number', false, null, 465],
                'username' => ['Utilisateur', 'text', false, null, null],
                'password' => ['Mot de passe', 'password', true, 'Pour Gmail : mot de passe d\'application', null],
                'from_address' => ['Adresse d\'expédition', 'email', false, 'Par défaut : l\'utilisateur SMTP', null],
                'from_name' => ['Nom affiché', 'text', false, 'Par défaut : le nom de l\'agence', null],
            ]],
            'LOG' => ['libelle' => 'Journal uniquement (tests)', 'champs' => []],
        ]],
        'SMS' => ['libelle' => 'SMS', 'icone' => 'ti-message', 'fournisseurs' => [
            'ORANGE_CI' => ['libelle' => 'Orange SMS API (Côte d\'Ivoire)', 'champs' => [
                'client_id' => ['Client ID', 'text', false, 'developer.orange.com › My apps', null],
                'client_secret' => ['Client secret', 'password', true, null, null],
                'expediteur' => ['Numéro expéditeur (tel:+225…)', 'text', false, 'Numéro de l\'application, ex. tel:+2250000', 'tel:+2250000'],
                'nom_expediteur' => ['Nom affiché (sender name)', 'text', false, 'Nom validé par Orange', null],
            ]],
            'TWILIO' => ['libelle' => 'Twilio', 'champs' => [
                'account_sid' => ['Account SID', 'text', false, null, null], 'auth_token' => ['Auth token', 'password', true, null, null],
                'expediteur' => ['Numéro ou nom expéditeur', 'text', false, null, null],
            ]],
            'INFOBIP' => ['libelle' => 'Infobip', 'champs' => [
                'base_url' => ['URL de base', 'url', false, 'ex. https://xxxxx.api.infobip.com', null], 'api_key' => ['Clé d\'API', 'password', true, null, null],
                'expediteur' => ['Expéditeur', 'text', false, null, null],
            ]],
            'SYMTEL' => ['libelle' => 'Symtel', 'champs' => [
                'url' => ['URL', 'url', false, null, 'https://www.symtel.biz/fr/index.php?mod=cgibin&page=2'], 'user' => ['Utilisateur', 'text', false, null, null],
                'code' => ['Code', 'password', true, null, null], 'expediteur' => ['Titre (expéditeur)', 'text', false, null, null],
            ]],
            'HTTP' => ['libelle' => 'Passerelle HTTP générique', 'champs' => [
                'url' => ['URL d\'envoi', 'url', false, null, null], 'token' => ['Jeton (Bearer)', 'password', true, null, null],
                'champ_telephone' => ['Nom du champ téléphone', 'text', false, null, 'to'], 'champ_message' => ['Nom du champ message', 'text', false, null, 'message'],
                'champ_expediteur' => ['Nom du champ expéditeur', 'text', false, null, 'from'], 'expediteur' => ['Expéditeur', 'text', false, null, null],
            ]],
            'LOG' => ['libelle' => 'Journal uniquement (tests)', 'champs' => []],
        ], 'communs' => ['indicatif' => ['Indicatif pays par défaut', 'text', false, 'Ajouté aux numéros locaux', '225']]],
        'WHATSAPP' => ['libelle' => 'WhatsApp', 'icone' => 'ti-brand-whatsapp', 'fournisseurs' => [
            'META' => ['libelle' => 'WhatsApp Cloud API (Meta)', 'champs' => [
                'phone_number_id' => ['Phone number ID', 'text', false, 'Meta for Developers › WhatsApp › API Setup', null],
                'access_token' => ['Jeton d\'accès permanent', 'password', true, null, null],
                'version' => ['Version de l\'API Graph', 'text', false, null, 'v20.0'],
            ]],
            'TWILIO' => ['libelle' => 'Twilio WhatsApp', 'champs' => [
                'account_sid' => ['Account SID', 'text', false, null, null], 'auth_token' => ['Auth token', 'password', true, null, null],
                'expediteur' => ['Numéro WhatsApp (whatsapp:+…)', 'text', false, null, null],
            ]],
            'LOG' => ['libelle' => 'Journal uniquement (tests)', 'champs' => []],
        ], 'communs' => ['indicatif' => ['Indicatif pays par défaut', 'text', false, null, '225']]],
    ];

    public static function definition(string $code): array
    {
        return self::CANAUX[$code] ?? abort(404);
    }

    /** @return array<string, array> champs du fournisseur + champs communs */
    public static function champs(string $code, ?string $fournisseur): array
    {
        $def = self::definition($code);
        $fournisseur ??= array_key_first($def['fournisseurs']);

        return ($def['fournisseurs'][$fournisseur]['champs'] ?? []) + ($def['communs'] ?? []);
    }
}
