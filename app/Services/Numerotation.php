<?php

namespace App\Services;

use App\Models\Sequence;
use App\Support\AgenceCourante;
use Illuminate\Support\Facades\DB;

/**
 * Numérotation séquentielle par agence et par année, sans trou ni doublon
 * (verrou ligne). Le préfixe est paramétrable : numerotation.<cle>.
 *
 *   Numerotation::suivant('vente')  // CV-2026-00001
 */
class Numerotation
{
    public function __construct(protected AgenceCourante $agenceCourante, protected Parametres $parametres)
    {
    }

    public function suivant(string $cle, ?int $annee = null): string
    {
        $agenceId = $this->agenceCourante->id() ?? throw new \LogicException('Aucune agence courante');
        $annee ??= (int) now()->format('Y');

        $numero = DB::transaction(function () use ($agenceId, $cle, $annee) {
            $sequence = Sequence::where(['agence_id' => $agenceId, 'cle' => $cle, 'annee' => $annee])->lockForUpdate()->first();

            if (!$sequence) {
                $sequence = Sequence::create(['agence_id' => $agenceId, 'cle' => $cle, 'annee' => $annee, 'dernier_numero' => 0]);
                $sequence = Sequence::whereKey($sequence->id)->lockForUpdate()->first();
            }

            $sequence->increment('dernier_numero');

            return $sequence->dernier_numero;
        });

        $prefixe = $this->parametres->get("numerotation.$cle") ?: strtoupper($cle);

        return sprintf('%s-%d-%05d', $prefixe, $annee, $numero);
    }
}
