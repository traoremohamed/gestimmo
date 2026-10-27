<?php

namespace App\Services\Location;

use App\Enums\NatureLoyer;
use App\Enums\StatutPaiement;
use App\Enums\StatutReversement;
use App\Models\Depense;
use App\Models\Loyer;
use App\Models\Reversement;
use App\Models\ReversementLigne;
use App\Models\Tiers;
use App\Services\Numerotation;
use App\Services\Parametres;
use App\Support\Montant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reversement au propriétaire des loyers encaissés, déduction faite de la
 * commission de gestion (taux du mandat) et des dépenses refacturables.
 */
class ReversementService
{
    public function __construct(protected Numerotation $numerotation, protected Parametres $parametres)
    {
    }

    /**
     * Loyers soldés, non encore reversés, des biens du propriétaire jusqu'à $fin.
     */
    public function loyersAReverser(Tiers $proprietaire, Carbon $fin)
    {
        return Loyer::with('bail.bien', 'bail.mandat')
            ->where('reverse', false)
            ->whereNotIn('id', ReversementLigne::whereNotNull('loyer_id')->select('loyer_id')) // déjà dans un brouillon
            ->where('statut', StatutPaiement::Paye->value)
            ->whereIn('nature', [NatureLoyer::Loyer->value, NatureLoyer::Avance->value, NatureLoyer::Penalite->value, NatureLoyer::Regularisation->value, NatureLoyer::Retenue->value])
            ->whereDate('date_echeance', '<=', $fin)
            ->whereHas('bail.bien', fn ($q) => $q->where('proprietaire_id', $proprietaire->id))
            ->orderBy('date_echeance')
            ->get();
    }

    public function depensesADeduire(Tiers $proprietaire, Carbon $fin)
    {
        return Depense::whereNull('reversement_id')
            ->where('refacturable_proprietaire', true)
            ->where('statut', 'payee')
            ->whereDate('date_depense', '<=', $fin)
            ->whereHas('bien', fn ($q) => $q->where('proprietaire_id', $proprietaire->id))
            ->get();
    }

    public function preparer(Tiers $proprietaire, Carbon $debut, Carbon $fin): Reversement
    {
        $loyers = $this->loyersAReverser($proprietaire, $fin);
        $depenses = $this->depensesADeduire($proprietaire, $fin);

        if ($loyers->isEmpty()) {
            throw new DomainException('Aucun loyer encaissé à reverser pour ce propriétaire sur la période.');
        }

        return DB::transaction(function () use ($proprietaire, $debut, $fin, $loyers, $depenses) {
            $reversement = Reversement::create([
                'numero' => $this->numerotation->suivant('reversement'),
                'proprietaire_id' => $proprietaire->id,
                'mandat_id' => $loyers->first()->bail->mandat_id,
                'periode_debut' => $debut,
                'periode_fin' => $fin,
                'statut' => StatutReversement::Brouillon,
                'created_by' => auth()->id(),
            ]);

            $encaisse = $commission = '0.00';
            foreach ($loyers as $loyer) {
                $mandat = $loyer->bail->mandat;
                $taux = $mandat?->taux_commission ?? $this->parametres->get('location.taux_commission_gestion');
                $base = $loyer->nature === NatureLoyer::Loyer && !($mandat?->commission_sur_charges)
                    ? $loyer->montant_loyer
                    : $loyer->montant;
                // pas de commission sur les retenues de sortie (réparations, factures) : elles financent la remise en état
                $montantCommission = $loyer->nature === NatureLoyer::Retenue ? '0.00' : Montant::pourcentage($base, $taux);

                $reversement->lignes()->create([
                    'loyer_id' => $loyer->id,
                    'libelle' => $loyer->libelle . ' — ' . $loyer->bail->bien->reference,
                    'montant_encaisse' => $loyer->montant_paye,
                    'taux_commission' => $taux,
                    'montant_commission' => $montantCommission,
                ]);

                $encaisse = Montant::add($encaisse, $loyer->montant_paye);
                $commission = Montant::add($commission, $montantCommission);
            }

            $deductions = Montant::normaliser($depenses->sum('montant'));
            Depense::whereIn('id', $depenses->pluck('id'))->update(['reversement_id' => $reversement->id]);

            $reversement->update([
                'montant_encaisse' => $encaisse,
                'montant_commission' => $commission,
                'montant_deductions' => $deductions,
                'montant_net' => Montant::sub(Montant::sub($encaisse, $commission), $deductions),
            ]);

            return $reversement;
        });
    }

    public function valider(Reversement $reversement): void
    {
        if ($reversement->statut !== StatutReversement::Brouillon) {
            throw new DomainException('Ce reversement est déjà validé.');
        }

        DB::transaction(function () use ($reversement) {
            Loyer::whereIn('id', $reversement->lignes()->pluck('loyer_id'))->update(['reverse' => true]);
            $reversement->update(['statut' => StatutReversement::Valide]);
        });
    }

    public function payer(Reversement $reversement, string $modePaiement, ?string $reference = null): void
    {
        if ($reversement->statut !== StatutReversement::Valide) {
            throw new DomainException('Le reversement doit être validé avant d\'être payé.');
        }

        $reversement->update([
            'statut' => StatutReversement::Paye,
            'mode_paiement' => $modePaiement,
            'reference_paiement' => $reference,
            'paye_le' => now(),
        ]);

        $compta = app(\App\Services\Compta\ComptaService::class);
        $compta->tenter(fn () => $compta->reversement($reversement));
    }

    public function supprimerBrouillon(Reversement $reversement): void
    {
        if ($reversement->statut !== StatutReversement::Brouillon) {
            throw new DomainException('Seul un brouillon peut être supprimé.');
        }

        DB::transaction(function () use ($reversement) {
            Depense::where('reversement_id', $reversement->id)->update(['reversement_id' => null]);
            $reversement->delete();
        });
    }
}
