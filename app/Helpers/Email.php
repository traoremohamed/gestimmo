<?php

namespace App\Helpers;

use App\Services\Integration\NotificationService;

/**
 * Envoi d'e-mails du socle, routé vers le canal e-mail configuré à l'écran (Intégrations & messages).
 * Sans canal configuré, le mailer du .env est utilisé.
 */
class Email
{
    /**
     * Envoi immédiat dans le gabarit de l'agence. $titre est conservé pour compatibilité (le sujet sert de titre).
     */
    public static function get_envoimailTemplate($email, $nom, $messages, $sujet, $titre = null, array $cc = []): void
    {
        NotificationService::email((string) $email, (string) $sujet, (string) $messages, $nom);
    }

    /**
     * Envoi différé via la file d'attente (php artisan queue:work --queue=notifications).
     */
    public static function envoyerEnFile($email, $nom, $messages, $sujet, $titre = null, array $cc = []): void
    {
        NotificationService::enFile('EMAIL', (string) $email, (string) $messages, (string) $sujet, $nom);
    }
}
