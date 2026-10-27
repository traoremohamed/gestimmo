<?php

namespace App\Models\Concerns;

/**
 * Contrat des dettes encaissables (implémenté via le trait EstPayable).
 */
interface Payable
{
    public function montantDu(): string;

    public function resteAPayer(): string;

    public function imputer(string $montant): void;

    public function desimputer(string $montant): void;

    public function libelleAffectation(): string;

    public function activite(): string;
}
