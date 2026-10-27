<?php

use App\Services\Parametres;
use App\Support\AgenceCourante;
use App\Support\Montant;

if (!function_exists('parametre')) {
    /**
     * Paramètre métier de l'agence courante : parametre('vente.taux_acompte').
     */
    function parametre(string $cle, mixed $defaut = null): mixed
    {
        return app(Parametres::class)->get($cle, $defaut);
    }
}

if (!function_exists('agence')) {
    function agence(): ?\App\Models\Agence
    {
        return app(AgenceCourante::class)->agence();
    }
}

if (!function_exists('montant')) {
    /**
     * Montant formaté avec la devise de l'agence : montant(1500000) => « 1 500 000 FCFA ».
     */
    function montant($valeur, bool $avecDevise = true): string
    {
        return Montant::formater($valeur ?? 0, $avecDevise ? (parametre('general.devise') ?: agence()?->devise ?: 'FCFA') : null);
    }
}

if (!function_exists('date_fr')) {
    function date_fr($date, string $format = 'd/m/Y'): string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->format($format) : '';
    }
}
