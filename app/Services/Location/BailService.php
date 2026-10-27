<?php

namespace App\Services\Location;

use App\Enums\NatureLoyer;
use App\Enums\RoleTiers;
use App\Enums\StatutBail;
use App\Enums\StatutBien;
use App\Enums\StatutPaiement;
use App\Models\Bail;
use App\Models\Bien;
use App\Models\FraisType;
use App\Models\Loyer;
use App\Models\Tiers;
use App\Services\Numerotation;
use App\Services\Parametres;
use App\Support\Montant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'un bail et génération des avis d'échéance (loyers).
 *
 * A l'activation : dépôt de garantie, frais d'agence, frais de contrat, puis
 * les N premiers loyers payés d'avance (exigibles à la signature).
 * Ensuite : génération mensuelle idempotente, pénalités de retard, révision, résiliation.
 */
class BailService
{
    public function __construct(protected Numerotation $numerotation, protected Parametres $parametres)
    {
    }

    /**
     * Brouillon de bail pré-rempli avec les conditions du bien et les paramètres de l'agence.
     */
    public function creer(Bien $bien, Tiers $locataire, array $donnees = []): Bail
    {
        if (!$bien->a_louer) {
            throw new DomainException("Le bien {$bien->reference} n'est pas proposé à la location.");
        }

        $loyer = Montant::normaliser($donnees['loyer_mensuel'] ?? $bien->loyer_mensuel);
        $moisCaution = (int) ($donnees['mois_caution'] ?? $this->parametres->get('location.mois_caution'));
        $dateDebut = Carbon::parse($donnees['date_debut'] ?? today());
        $duree = (int) ($donnees['duree_mois'] ?? $this->parametres->get('location.duree_bail_mois'));

        return Bail::create(array_merge([
            'bien_id' => $bien->id,
            'locataire_id' => $locataire->id,
            'mandat_id' => $bien->mandat_id,
            'statut' => StatutBail::Brouillon,
            'date_debut' => $dateDebut,
            'duree_mois' => $duree,
            'date_fin' => $dateDebut->copy()->addMonthsNoOverflow($duree)->subDay(),
            'loyer_mensuel' => $loyer,
            'charges_mensuelles' => Montant::normaliser($donnees['charges_mensuelles'] ?? $bien->charges_mensuelles),
            'jour_echeance' => $this->parametres->get('location.jour_echeance'),
            'mois_caution' => $moisCaution,
            'mois_avance' => $this->parametres->get('location.mois_avance'),
            'depot_garantie' => Montant::mul($loyer, $moisCaution),
            'frais_agence' => Montant::mul($loyer, $this->parametres->get('location.mois_frais_agence')),
            'taux_penalite' => $this->parametres->get('location.taux_penalite_retard'),
            'created_by' => auth()->id(),
        ], array_filter($donnees, fn ($v) => $v !== null && $v !== '')));
    }

    public function activer(Bail $bail): Bail
    {
        if ($bail->statut !== StatutBail::Brouillon) {
            throw new DomainException('Seul un bail en brouillon peut être activé.');
        }

        return DB::transaction(function () use ($bail) {
            $bien = Bien::whereKey($bail->bien_id)->lockForUpdate()->firstOrFail();
            if (!$bien->estDisponible()) {
                throw new DomainException("Le bien {$bien->reference} n'est pas disponible ({$bien->statut->libelle()}).");
            }

            $bail->forceFill(['numero' => $this->numerotation->suivant('bail'), 'statut' => StatutBail::Actif])->save();
            $bien->update(['statut' => StatutBien::Loue]);
            $bail->locataire->ajouterRole(RoleTiers::Locataire);

            $signature = $bail->date_debut;

            if (Montant::estPositif($bail->depot_garantie)) {
                $this->creerLoyer($bail, NatureLoyer::DepotGarantie, 'Dépôt de garantie (' . $bail->mois_caution . ' mois)', $signature, $bail->depot_garantie);
            }
            // cautions de compteurs perçues par l'agence, restituées (ou imputées) au décompte de sortie
            if ($this->parametres->get('sortie.cautions_compteurs')) {
                if (Montant::estPositif($bail->caution_sodeci)) {
                    $this->creerLoyer($bail, NatureLoyer::CautionSodeci, 'Caution compteur SODECI', $signature, $bail->caution_sodeci);
                }
                if (Montant::estPositif($bail->caution_cie)) {
                    $this->creerLoyer($bail, NatureLoyer::CautionCie, 'Caution compteur CIE', $signature, $bail->caution_cie);
                }
            }
            if (Montant::estPositif($bail->frais_agence)) {
                $this->creerLoyer($bail, NatureLoyer::FraisAgence, 'Frais d\'agence', $signature, $bail->frais_agence);
            }
            foreach (FraisType::activite('location')->where('obligatoire', true)->get() as $frais) {
                $this->creerLoyer($bail, NatureLoyer::FraisAgence, $frais->libelle, $signature, $frais->montantPour($bail->loyer_mensuel));
            }

            // les mois d'avance sont générés immédiatement, exigibles à la signature
            $finAvance = $signature->copy()->addMonthsNoOverflow(max(1, (int) $bail->mois_avance) - 1);
            $this->genererLoyers($bail, $finAvance->max(today()));

            return $bail->refresh();
        });
    }

    /**
     * Génère les loyers des périodes commençant au plus tard à $jusquAu (idempotent).
     */
    public function genererLoyers(Bail $bail, ?Carbon $jusquAu = null): int
    {
        if ($bail->statut !== StatutBail::Actif) {
            return 0;
        }

        $jusquAu = ($jusquAu ?? today())->copy()->endOfDay();
        $periodicite = max(1, (int) $bail->periodicite_mois);
        $debut = $bail->loyers_generes_jusqu_au ? $bail->loyers_generes_jusqu_au->copy()->addDay() : $bail->date_debut->copy();
        $limite = $bail->date_resiliation ?? ($bail->tacite_reconduction ? null : $bail->date_fin);
        $nbAvance = (int) $bail->mois_avance;
        $crees = 0;

        while ($debut->lte($jusquAu) && ($limite === null || $debut->lte($limite))) {
            $fin = $debut->copy()->addMonthsNoOverflow($periodicite)->subDay();
            $moisEcoules = ($debut->year - $bail->date_debut->year) * 12 + $debut->month - $bail->date_debut->month;
            $estAvance = $moisEcoules < $nbAvance;

            $existe = Loyer::where('bail_id', $bail->id)->where('nature', NatureLoyer::Loyer->value)->whereDate('periode_debut', $debut)->exists();
            if (!$existe) {
                $echeance = $estAvance ? $bail->date_debut->copy() : $this->dateEcheance($debut, (int) $bail->jour_echeance);
                Loyer::create([
                    'agence_id' => $bail->agence_id,
                    'bail_id' => $bail->id,
                    'nature' => NatureLoyer::Loyer,
                    'periode_debut' => $debut,
                    'periode_fin' => $fin,
                    'libelle' => 'Loyer ' . $debut->locale('fr')->translatedFormat($periodicite === 1 ? 'F Y' : 'M Y') . ($periodicite > 1 ? ' – ' . $fin->locale('fr')->translatedFormat('M Y') : '') . ($estAvance ? ' (avance)' : ''),
                    'date_echeance' => $echeance,
                    'montant_loyer' => Montant::mul($bail->loyer_mensuel, $periodicite),
                    'montant_charges' => Montant::mul($bail->charges_mensuelles, $periodicite),
                    'montant' => $bail->montantPeriode(),
                    'statut' => StatutPaiement::APayer,
                ]);
                $crees++;
            }

            $bail->loyers_generes_jusqu_au = $fin;
            $debut = $fin->copy()->addDay();
        }

        $bail->save();

        return $crees;
    }

    /**
     * Génération pour tous les baux actifs de l'agence courante (tâche planifiée mensuelle).
     */
    public function genererPourTous(?Carbon $jusquAu = null): int
    {
        $total = 0;
        Bail::where('statut', StatutBail::Actif->value)->each(function (Bail $bail) use ($jusquAu, &$total) {
            $total += DB::transaction(fn () => $this->genererLoyers($bail, $jusquAu));
        });

        return $total;
    }

    /**
     * Pénalité de retard sur les loyers impayés au-delà du délai de grâce (une seule par loyer).
     */
    public function appliquerPenalites(?Carbon $date = null): int
    {
        $date ??= today();
        $grace = (int) $this->parametres->get('location.delai_grace_jours');
        $creees = 0;

        $loyers = Loyer::with('bail')->where('nature', NatureLoyer::Loyer->value)->impayes()
            ->whereDate('date_echeance', '<', $date->copy()->subDays($grace))
            ->whereHas('bail', fn ($q) => $q->where('statut', StatutBail::Actif->value)->where('taux_penalite', '>', 0))
            ->get();

        foreach ($loyers as $loyer) {
            $dejaPenalise = Loyer::where('bail_id', $loyer->bail_id)->where('nature', NatureLoyer::Penalite->value)
                ->whereDate('periode_debut', $loyer->periode_debut)->exists();
            if ($dejaPenalise) {
                continue;
            }

            $this->creerLoyer($loyer->bail, NatureLoyer::Penalite, 'Pénalité de retard — ' . $loyer->libelle, $date,
                Montant::pourcentage($loyer->montant_loyer, $loyer->bail->taux_penalite), $loyer->periode_debut, $loyer->periode_fin);
            $creees++;
        }

        return $creees;
    }

    /**
     * Révision du loyer à la date anniversaire (taux de révision du bail).
     */
    public function reviser(Bail $bail, $nouveauLoyer = null): void
    {
        $nouveauLoyer = $nouveauLoyer !== null
            ? Montant::normaliser($nouveauLoyer)
            : Montant::add($bail->loyer_mensuel, Montant::pourcentage($bail->loyer_mensuel, $bail->taux_revision));

        $bail->update([
            'loyer_mensuel' => $nouveauLoyer,
            'date_prochaine_revision' => ($bail->date_prochaine_revision ?? today())->copy()->addYear(),
        ]);
    }

    /**
     * Résiliation : les loyers des périodes postérieures non réglés sont annulés, le bien est libéré.
     */
    public function resilier(Bail $bail, Carbon $date, string $motif, $depotRestitue = null): Bail
    {
        if ($bail->statut !== StatutBail::Actif) {
            throw new DomainException('Seul un bail actif peut être résilié.');
        }

        return DB::transaction(function () use ($bail, $date, $motif, $depotRestitue) {
            Loyer::where('bail_id', $bail->id)->where('nature', NatureLoyer::Loyer->value)
                ->whereDate('periode_debut', '>', $date)
                ->where('montant_paye', 0)
                ->update(['statut' => StatutPaiement::Annule->value]);

            $bail->update([
                'statut' => StatutBail::Resilie,
                'date_resiliation' => $date,
                'motif_resiliation' => $motif,
                'depot_restitue' => $depotRestitue !== null ? Montant::normaliser($depotRestitue) : null,
            ]);

            Bien::whereKey($bail->bien_id)->where('statut', StatutBien::Loue->value)->update(['statut' => StatutBien::Disponible->value]);

            return $bail;
        });
    }

    protected function creerLoyer(Bail $bail, NatureLoyer $nature, string $libelle, Carbon $echeance, $montant, $periodeDebut = null, $periodeFin = null): Loyer
    {
        return Loyer::create([
            'agence_id' => $bail->agence_id,
            'bail_id' => $bail->id,
            'nature' => $nature,
            'libelle' => $libelle,
            'periode_debut' => $periodeDebut,
            'periode_fin' => $periodeFin,
            'date_echeance' => $echeance,
            'montant' => Montant::normaliser($montant),
            'statut' => StatutPaiement::APayer,
        ]);
    }

    protected function dateEcheance(Carbon $debutPeriode, int $jour): Carbon
    {
        $jour = max(1, min($jour, $debutPeriode->daysInMonth));

        return $debutPeriode->copy()->day($jour);
    }
}
