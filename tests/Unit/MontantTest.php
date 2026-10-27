<?php

namespace Tests\Unit;

use App\Support\Montant;
use PHPUnit\Framework\TestCase;

class MontantTest extends TestCase
{
    public function test_normalise_les_saisies_avec_espaces_et_virgules(): void
    {
        $this->assertSame('1500000.50', Montant::normaliser('1 500 000,50'));
        $this->assertSame('0.00', Montant::normaliser(null));
    }

    public function test_la_repartition_egale_conserve_le_total(): void
    {
        $parts = Montant::repartirEgal('1000000', 3);

        $this->assertSame(['333333.33', '333333.33', '333333.34'], $parts);
        $this->assertSame('1000000.00', Montant::add(...$parts));
    }

    public function test_la_repartition_au_prorata_conserve_le_total(): void
    {
        $parts = Montant::repartirProrata('100000', ['a' => 333.3333, 'b' => 333.3333, 'c' => 333.3334]);

        $this->assertSame('100000.00', Montant::add(...array_values($parts)));
    }

    public function test_pourcentage_et_arrondi(): void
    {
        $this->assertSame('300000.00', Montant::pourcentage('1000000', 30));
        $this->assertSame('0.01', Montant::pourcentage('0.05', 10)); // 0.005 arrondi au centime supérieur
    }

    public function test_formatage(): void
    {
        $this->assertSame("1 500 000 FCFA", Montant::formater('1500000', 'FCFA'));
    }
}
