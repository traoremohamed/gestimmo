<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Cellules HTML échappées pour les listes (CrudController::colonnes()).
 */
class Cellule
{
    public static function statut(?\BackedEnum $statut): HtmlString
    {
        if (!$statut || !method_exists($statut, 'libelle')) {
            return new HtmlString('');
        }

        $texteSombre = in_array($statut->couleur(), ['warning', 'light', 'info'], true) ? ' text-dark' : '';

        return new HtmlString('<span class="badge bg-' . e($statut->couleur()) . $texteSombre . '">' . e($statut->libelle()) . '</span>');
    }

    public static function badge(?string $texte, string $couleur = 'secondary'): HtmlString
    {
        return new HtmlString($texte ? '<span class="badge bg-' . e($couleur) . '">' . e($texte) . '</span>' : '');
    }

    public static function actif(bool $actif): HtmlString
    {
        return static::badge($actif ? 'Actif' : 'Inactif', $actif ? 'success' : 'secondary');
    }

    public static function oui(bool $valeur): HtmlString
    {
        return new HtmlString($valeur ? '<i class="ti ti-check text-success"></i>' : '<i class="ti ti-minus text-muted"></i>');
    }

    public static function lien(?string $texte, string $url): HtmlString
    {
        return new HtmlString($texte ? '<a href="' . e($url) . '">' . e($texte) . '</a>' : '');
    }

    public static function montant($valeur): HtmlString
    {
        return new HtmlString('<span class="montant">' . e(montant($valeur)) . '</span>');
    }

    public static function deuxLignes(?string $principal, ?string $secondaire): HtmlString
    {
        return new HtmlString('<div class="fw-medium">' . e($principal) . '</div>' . ($secondaire ? '<div class="small text-muted">' . e($secondaire) . '</div>' : ''));
    }
}
