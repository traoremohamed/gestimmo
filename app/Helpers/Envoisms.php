<?php

namespace App\Helpers;

use App\Services\Integration\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envoi de SMS du socle, routé vers le canal SMS configuré à l'écran (Intégrations & messages).
 * Sans canal configuré, la passerelle de config/rbac.php (clé sms) est utilisée :
 *  - symtel : passerelle Symtel
 *  - http   : passerelle générique (POST JSON + Bearer token)
 *  - log    : n'envoie rien, écrit le SMS dans les logs (développement)
 */
class Envoisms
{
    /**
     * Envoi immédiat. Retourne true si la passerelle a accepté le message (ou l'a simulé en sandbox).
     */
    public static function sendSMS(string $tel, string $content): bool
    {
        return in_array(NotificationService::sms($tel, $content), ['ENVOYE', 'SIMULE'], true);
    }

    /**
     * Envoi différé via la file d'attente (php artisan queue:work --queue=notifications).
     */
    public static function envoyerEnFile(string $tel, string $content): void
    {
        NotificationService::enFile('SMS', $tel, $content);
    }

    /**
     * Ajoute l'indicatif pays si absent : 0102030405 -> 2250102030405
     */
    public static function formatNumero(string $tel): string
    {
        return NotificationService::numero($tel, (string) config('rbac.sms.indicatif', '225'));
    }

    /**
     * Passerelle du .env, utilisée quand aucun canal SMS n'est configuré à l'écran. Lève une exception en cas de refus.
     */
    public static function viaConfiguration(string $phone, string $content): ?string
    {
        $ok = match (config('rbac.sms.driver', 'log')) {
            'symtel' => static::viaSymtel($phone, $content),
            'http' => static::viaHttp($phone, $content),
            default => static::viaLog($phone, $content),
        };
        if (!$ok) {
            throw new RuntimeException('La passerelle SMS a refusé le message.');
        }

        return null;
    }

    protected static function viaSymtel(string $phone, string $content): bool
    {
        $conf = config('rbac.sms.symtel');

        return Http::asForm()->timeout(config('rbac.sms.timeout', 15))->post($conf['url'], [
            'user' => $conf['user'],
            'code' => $conf['code'],
            'title' => config('rbac.sms.sender'),
            'phone' => $phone,
            'content' => $content,
        ])->successful();
    }

    protected static function viaHttp(string $phone, string $content): bool
    {
        $conf = config('rbac.sms.http');
        $fields = $conf['fields'];

        return Http::withToken((string) $conf['token'])
            ->timeout(config('rbac.sms.timeout', 15))
            ->post($conf['url'], [
                $fields['phone'] => $phone,
                $fields['message'] => $content,
                $fields['sender'] => config('rbac.sms.sender'),
            ])->successful();
    }

    protected static function viaLog(string $phone, string $content): bool
    {
        Log::info('[SMS] ' . $phone . ' : ' . $content);

        return true;
    }
}
