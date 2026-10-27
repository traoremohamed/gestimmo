<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Aide contextuelle des écrans (config/aide.php) : route exacte, sinon motif avec joker, alias « @route ».
 */
class Aide
{
    public static function pour(?string $route): ?array
    {
        if (!$route) {
            return null;
        }

        $pages = config('aide.pages', []);
        $cle = isset($pages[$route]) ? $route : collect(array_keys($pages))->first(fn ($m) => str_contains($m, '*') && Str::is($m, $route));

        return $cle ? self::resoudre($pages[$cle], $pages) : null;
    }

    protected static function resoudre(array|string $entree, array $pages, int $profondeur = 0): ?array
    {
        if (is_string($entree)) {
            $cible = ltrim($entree, '@');

            return $profondeur < 3 && isset($pages[$cible]) ? self::resoudre($pages[$cible], $pages, $profondeur + 1) : null;
        }

        return $entree + ['objectif' => null, 'etapes' => [], 'regles' => [], 'chapitre' => null];
    }
}
