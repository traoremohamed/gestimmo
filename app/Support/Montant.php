<?php

namespace App\Support;

/**
 * Calculs monétaires exacts (bcmath, 2 décimales) : les montants ne passent
 * jamais par des flottants (l'ancienne base les stockait en double / varchar).
 */
class Montant
{
    public const ECHELLE = 2;

    public static function normaliser(int|float|string|null $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '0.00';
        }

        // saisie « 1 500 000 » ou « 1500000,50 »
        $valeur = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], (string) $valeur);

        if (!is_numeric($valeur)) {
            throw new \InvalidArgumentException("Montant invalide : $valeur");
        }

        return bcadd($valeur, '0', self::ECHELLE);
    }

    public static function add(...$valeurs): string
    {
        return array_reduce($valeurs, fn ($total, $v) => bcadd($total, self::normaliser($v), self::ECHELLE), '0.00');
    }

    public static function sub($a, $b): string
    {
        return bcsub(self::normaliser($a), self::normaliser($b), self::ECHELLE);
    }

    public static function mul($a, $b): string
    {
        return self::arrondir(bcmul(self::normaliser($a), (string) $b, 6));
    }

    /**
     * Pourcentage arrondi : pourcentage(1 000 000, 30) = 300 000.00
     */
    public static function pourcentage($montant, $taux): string
    {
        return self::arrondir(bcdiv(bcmul(self::normaliser($montant), self::normaliser($taux), 6), '100', 6));
    }

    public static function compare($a, $b): int
    {
        return bccomp(self::normaliser($a), self::normaliser($b), self::ECHELLE);
    }

    public static function min($a, $b): string
    {
        return self::compare($a, $b) <= 0 ? self::normaliser($a) : self::normaliser($b);
    }

    public static function estPositif($a): bool
    {
        return self::compare($a, 0) > 0;
    }

    /**
     * Répartit un total en $n parts égales ; l'écart d'arrondi est porté par la dernière part,
     * la somme des parts est toujours égale au total.
     *
     * @return string[]
     */
    public static function repartirEgal($total, int $n): array
    {
        if ($n <= 0) {
            return [];
        }

        $total = self::normaliser($total);
        $part = bcdiv($total, (string) $n, self::ECHELLE);
        $parts = array_fill(0, $n, $part);
        $parts[$n - 1] = bcsub($total, bcmul($part, (string) ($n - 1), self::ECHELLE), self::ECHELLE);

        return $parts;
    }

    /**
     * Répartit un total au prorata de poids (tantièmes) ; la plus grande part absorbe l'arrondi.
     *
     * @param  array<int|string, int|float|string>  $poids
     * @return array<int|string, string>
     */
    public static function repartirProrata($total, array $poids): array
    {
        $total = self::normaliser($total);
        $somme = array_reduce($poids, fn ($s, $p) => bcadd($s, (string) $p, 6), '0');

        if (bccomp($somme, '0', 6) <= 0) {
            return array_map(fn () => '0.00', $poids);
        }

        $parts = [];
        foreach ($poids as $cle => $p) {
            $parts[$cle] = self::arrondir(bcdiv(bcmul($total, (string) $p, 8), $somme, 8));
        }

        $ecart = bcsub($total, array_reduce($parts, fn ($s, $v) => bcadd($s, $v, self::ECHELLE), '0.00'), self::ECHELLE);
        if (bccomp($ecart, '0', self::ECHELLE) !== 0) {
            $cleMax = array_search(max(array_map('floatval', $poids)), array_map('floatval', $poids));
            $parts[$cleMax] = bcadd($parts[$cleMax], $ecart, self::ECHELLE);
        }

        return $parts;
    }

    /**
     * Arrondi commercial au centime (bcmath tronque par défaut).
     */
    public static function arrondir(string $valeur): string
    {
        $correction = bccomp($valeur, '0', 6) >= 0 ? '0.005' : '-0.005';

        return bcadd(bcadd($valeur, $correction, 6), '0', self::ECHELLE);
    }

    public static function formater($valeur, ?string $devise = null): string
    {
        $valeur = (float) self::normaliser($valeur);
        $decimales = fmod($valeur, 1.0) == 0.0 ? 0 : 2;

        return number_format($valeur, $decimales, ',', ' ') . ($devise ? ' ' . $devise : '');
    }
}
