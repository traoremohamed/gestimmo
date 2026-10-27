<?php

namespace App\Helpers;

use Hashids\Hashids;
use Illuminate\Support\Facades\Log;

class Crypt
{
    protected static ?Hashids $hashids = null;

    protected static function hashids(): Hashids
    {
        return static::$hashids ??= new Hashids(
            (string) config('rbac.hashids.salt'),
            (int) config('rbac.hashids.length', 10),
            (string) config('rbac.hashids.alphabet')
        );
    }

    /**
     * Crypte un ID (court et rapide)
     * Exemple: 123 -> "aBc12xyz"
     */
    public static function UrlCrypt($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            if (is_numeric($value)) {
                return static::hashids()->encode((int) $value);
            }

            $numbers = array_map('ord', str_split((string) $value));

            return static::hashids()->encode(...$numbers);
        } catch (\Exception $e) {
            Log::error('Erreur cryptage HashId', ['value' => $value, 'message' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Décrypte un ID
     * Exemple: "aBc12xyz" -> 123
     */
    public static function UrldeCrypt($hash)
    {
        if (!$hash) {
            return null;
        }

        try {
            $decoded = static::hashids()->decode($hash);

            if (empty($decoded)) {
                return null;
            }

            if (count($decoded) === 1) {
                return $decoded[0];
            }

            return implode('', array_map('chr', $decoded));
        } catch (\Exception $e) {
            Log::warning('Tentative décryptage invalide', ['hash' => $hash, 'ip' => request()->ip()]);

            return null;
        }
    }

    /**
     * Crypte plusieurs IDs en une seule string
     */
    public static function UrlCryptMultiple(array $identifiants): string
    {
        if (empty($identifiants)) {
            return '';
        }

        return static::hashids()->encode(...array_map('intval', $identifiants));
    }

    /**
     * Décrypte plusieurs IDs
     */
    public static function UrldeCryptMultiple($identifiant): ?array
    {
        if (empty($identifiant)) {
            return null;
        }

        $decoded = static::hashids()->decode($identifiant);

        return !empty($decoded) ? $decoded : null;
    }

    /**
     * Génère un mot de passe sécurisé
     */
    public static function MotDePasse(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = $chars[random_int(0, 25)]
            . $chars[random_int(26, 51)]
            . $chars[random_int(52, 61)]
            . $chars[random_int(62, strlen($chars) - 1)];

        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Vérifie la force d'un mot de passe
     */
    public static function VerifierMotDePasse(?string $motDePasse): string
    {
        if (empty($motDePasse) || strlen($motDePasse) < 8) {
            return "Le mot de passe doit contenir au moins 8 caractères.";
        }

        if (!preg_match('/[0-9]/', $motDePasse)) {
            return "Le mot de passe doit contenir au moins un chiffre.";
        }

        if (!preg_match('/[a-z]/', $motDePasse) || !preg_match('/[A-Z]/', $motDePasse)) {
            return "Le mot de passe doit contenir des lettres majuscules et minuscules.";
        }

        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $motDePasse)) {
            return "Le mot de passe doit contenir au moins un caractère spécial.";
        }

        return "mot de passe correcte";
    }
}
