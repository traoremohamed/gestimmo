<?php

namespace App\Services\Maintenance;

use App\Enums\StatutIntervention;
use App\Enums\StatutReclamation;
use App\Models\Depense;
use App\Models\Intervention;
use App\Services\Numerotation;
use App\Support\Montant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InterventionService
{
    public function __construct(protected Numerotation $numerotation)
    {
    }

    public function creer(array $donnees): Intervention
    {
        return Intervention::create($donnees + [
            'numero' => $this->numerotation->suivant('intervention'),
            'statut' => StatutIntervention::Signalee,
            'date_signalement' => today(),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Clôture : enregistre le coût réel en dépense (refacturable au propriétaire le cas échéant)
     * et résout la réclamation d'origine.
     */
    public function terminer(Intervention $intervention, $coutReel, ?Carbon $date = null, ?string $modePaiement = null): Intervention
    {
        if (in_array($intervention->statut, [StatutIntervention::Terminee, StatutIntervention::Annulee], true)) {
            throw new DomainException('Cette intervention est déjà clôturée.');
        }

        return DB::transaction(function () use ($intervention, $coutReel, $date, $modePaiement) {
            $depense = null;
            if (Montant::estPositif($coutReel ?? 0) && $intervention->a_charge_de !== 'locataire') {
                $depense = Depense::create([
                    'numero' => $this->numerotation->suivant('depense'),
                    'date_depense' => $date ?? today(),
                    'categorie' => 'TRAVAUX',
                    'libelle' => 'Intervention ' . $intervention->numero . ' — ' . $intervention->objet,
                    'montant' => Montant::normaliser($coutReel),
                    'fournisseur_id' => $intervention->fournisseur_id,
                    'copropriete_id' => $intervention->copropriete_id,
                    'bien_id' => $intervention->bien_id,
                    'refacturable_proprietaire' => $intervention->a_charge_de === 'proprietaire' && $intervention->bien_id !== null,
                    'mode_paiement' => $modePaiement,
                    'statut' => 'payee',
                    'created_by' => auth()->id(),
                ]);
            }

            $intervention->update([
                'statut' => StatutIntervention::Terminee,
                'date_realisation' => $date ?? today(),
                'cout_reel' => $coutReel !== null ? Montant::normaliser($coutReel) : null,
                'depense_id' => $depense?->id,
            ]);

            $intervention->reclamation?->update(['statut' => StatutReclamation::Resolue, 'resolue_le' => now()]);

            return $intervention;
        });
    }
}
