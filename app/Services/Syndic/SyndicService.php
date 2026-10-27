<?php

namespace App\Services\Syndic;

use App\Enums\RoleTiers;
use App\Enums\StatutAppelFonds;
use App\Enums\StatutAssemblee;
use App\Enums\StatutPaiement;
use App\Models\AppelFonds;
use App\Models\Assemblee;
use App\Models\Budget;
use App\Models\CleLot;
use App\Models\Copropriete;
use App\Models\Resolution;
use App\Models\Tiers;
use App\Services\Integration\NotificationService;
use App\Services\Numerotation;
use App\Services\Parametres;
use App\Support\Montant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Syndic de copropriété : budget prévisionnel, appels de fonds répartis
 * selon les tantièmes, votes en assemblée générale.
 */
class SyndicService
{
    public function __construct(protected Numerotation $numerotation, protected Parametres $parametres)
    {
    }

    public function ajouterLot(Copropriete $copropriete, array $donnees)
    {
        $lot = $copropriete->lots()->create($donnees);
        if ($lot->coproprietaire_id) {
            Tiers::find($lot->coproprietaire_id)?->ajouterRole(RoleTiers::Coproprietaire);
        }

        return $lot;
    }

    /**
     * @param  array<int, array{poste: string, libelle?: string, cle_repartition_id?: int|null, montant: mixed}>  $lignes
     */
    public function enregistrerBudget(Copropriete $copropriete, int $exercice, array $lignes): Budget
    {
        return DB::transaction(function () use ($copropriete, $exercice, $lignes) {
            $debut = Carbon::create($exercice, $copropriete->exercice_debut_mois ?: 1, 1);

            $budget = Budget::firstOrNew(['copropriete_id' => $copropriete->id, 'exercice' => $exercice]);
            if ($budget->exists && $budget->statut !== 'brouillon') {
                throw new DomainException('Ce budget est déjà voté : il ne peut plus être modifié.');
            }
            $budget->fill(['date_debut' => $debut, 'date_fin' => $debut->copy()->addYear()->subDay(), 'statut' => 'brouillon'])->save();

            $budget->lignes()->delete();
            $total = '0.00';
            foreach ($lignes as $ligne) {
                if (!Montant::estPositif($ligne['montant'] ?? 0)) {
                    continue;
                }
                $budget->lignes()->create([
                    'poste' => $ligne['poste'],
                    'libelle' => $ligne['libelle'] ?? null,
                    'cle_repartition_id' => $ligne['cle_repartition_id'] ?? null,
                    'montant' => Montant::normaliser($ligne['montant']),
                ]);
                $total = Montant::add($total, $ligne['montant']);
            }
            $budget->update(['montant_total' => $total]);

            return $budget;
        });
    }

    public function voterBudget(Budget $budget, ?Carbon $date = null): void
    {
        if ($budget->statut !== 'brouillon') {
            throw new DomainException('Ce budget est déjà voté.');
        }
        $budget->update(['statut' => 'vote', 'vote_le' => $date ?? today()]);
    }

    /**
     * Appels de provision sur budget voté : le budget est divisé en N appels par exercice,
     * un appel par clé de répartition, plus la cotisation au fonds de travaux.
     *
     * @return AppelFonds[]
     */
    public function genererAppelsProvision(Budget $budget, ?int $nombre = null): array
    {
        if ($budget->statut !== 'vote') {
            throw new DomainException('Le budget doit être voté avant de générer les appels de fonds.');
        }
        if ($budget->appels()->exists()) {
            throw new DomainException('Les appels de fonds de ce budget ont déjà été générés.');
        }

        $nombre ??= max(1, (int) $this->parametres->get('syndic.appels_par_an'));
        $copropriete = $budget->copropriete;
        $delai = (int) $this->parametres->get('syndic.delai_paiement_jours');
        $parCle = $budget->lignes()->get()->groupBy(fn ($l) => $l->cle_repartition_id ?? 0);

        return DB::transaction(function () use ($budget, $nombre, $copropriete, $delai, $parCle) {
            $appels = [];
            $moisParAppel = intdiv(12, $nombre) ?: 1;

            foreach ($parCle as $cleId => $lignes) {
                $parts = Montant::repartirEgal($lignes->sum('montant'), $nombre);
                foreach ($parts as $i => $montant) {
                    $dateAppel = $budget->date_debut->copy()->addMonthsNoOverflow($i * $moisParAppel);
                    $appels[] = AppelFonds::create([
                        'copropriete_id' => $copropriete->id,
                        'budget_id' => $budget->id,
                        'type' => 'provision',
                        'libelle' => 'Provision ' . ($i + 1) . '/' . $nombre . ' — exercice ' . $budget->exercice . ($cleId ? ' — ' . $lignes->first()->cle?->libelle : ''),
                        'cle_repartition_id' => $cleId ?: null,
                        'date_appel' => $dateAppel,
                        'date_echeance' => $dateAppel->copy()->addDays($delai),
                        'montant_total' => $montant,
                        'statut' => StatutAppelFonds::Brouillon,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $taux = (float) ($copropriete->taux_fonds_travaux ?: $this->parametres->get('syndic.taux_fonds_travaux'));
            if ($taux > 0) {
                $parts = Montant::repartirEgal(Montant::pourcentage($budget->montant_total, $taux), $nombre);
                foreach ($parts as $i => $montant) {
                    $dateAppel = $budget->date_debut->copy()->addMonthsNoOverflow($i * $moisParAppel);
                    $appels[] = AppelFonds::create([
                        'copropriete_id' => $copropriete->id,
                        'budget_id' => $budget->id,
                        'type' => 'fonds_travaux',
                        'libelle' => 'Fonds de travaux ' . ($i + 1) . '/' . $nombre . ' — exercice ' . $budget->exercice,
                        'date_appel' => $dateAppel,
                        'date_echeance' => $dateAppel->copy()->addDays($delai),
                        'montant_total' => $montant,
                        'statut' => StatutAppelFonds::Brouillon,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            return $appels;
        });
    }

    /**
     * Répartit l'appel entre les lots (tantièmes généraux ou de la clé spéciale) et l'émet.
     */
    public function emettre(AppelFonds $appel): AppelFonds
    {
        if ($appel->statut !== StatutAppelFonds::Brouillon) {
            throw new DomainException('Cet appel de fonds est déjà émis.');
        }

        return DB::transaction(function () use ($appel) {
            $lots = $appel->copropriete->lots()->get()->keyBy('id');

            $poids = $appel->cle_repartition_id
                ? CleLot::where('cle_repartition_id', $appel->cle_repartition_id)->pluck('tantiemes', 'copro_lot_id')->all()
                : $lots->pluck('tantiemes', 'id')->all();

            $poids = array_filter($poids, fn ($p) => (float) $p > 0);
            if (empty($poids)) {
                throw new DomainException('Aucun lot avec des tantièmes pour cette clé de répartition.');
            }

            foreach (Montant::repartirProrata($appel->montant_total, $poids) as $lotId => $montant) {
                $appel->lignes()->create([
                    'agence_id' => $appel->agence_id,
                    'copro_lot_id' => $lotId,
                    'coproprietaire_id' => $lots[$lotId]?->coproprietaire_id,
                    'tantiemes' => $poids[$lotId],
                    'montant' => $montant,
                    'statut' => StatutPaiement::APayer,
                ]);
            }

            $appel->update(['numero' => $this->numerotation->suivant('appel'), 'statut' => StatutAppelFonds::Emis]);
            $this->notifierAppel($appel);

            return $appel;
        });
    }

    /** Avis d'appel de fonds à chaque copropriétaire (modèle APPEL_FONDS), envoyé après validation. */
    protected function notifierAppel(AppelFonds $appel): void
    {
        try {
            foreach ($appel->lignes()->with(['coproprietaire', 'lot'])->get() as $ligne) {
                if ($tiers = $ligne->coproprietaire) {
                    NotificationService::notifier('APPEL_FONDS', ['email' => $tiers->email, 'telephone' => ($tiers->indicatif ?: '') . $tiers->telephone, 'nom' => $tiers->nom_complet], [
                        'copropriete' => $appel->copropriete->nom, 'libelle' => $appel->libelle, 'lot' => $ligne->lot?->numero,
                        'montant' => Montant::formater($ligne->montant), 'date' => $appel->date_echeance?->format('d/m/Y'),
                    ], $tiers);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function annulerAppel(AppelFonds $appel): void
    {
        if ($appel->lignes()->where('montant_paye', '>', 0)->exists()) {
            throw new DomainException('Des paiements ont été reçus sur cet appel : annulez d\'abord les encaissements.');
        }

        DB::transaction(function () use ($appel) {
            $appel->lignes()->update(['statut' => StatutPaiement::Annule->value]);
            $appel->update(['statut' => StatutAppelFonds::Annule]);
        });
    }

    /**
     * Enregistre le vote d'une résolution (voix en tantièmes) et détermine son adoption.
     */
    public function voter(Resolution $resolution, $pour, $contre, $abstention = 0): Resolution
    {
        $total = (float) $resolution->assemblee->copropriete->total_tantiemes;
        $pour = (float) $pour;
        $contre = (float) $contre;

        if ($pour + $contre + (float) $abstention > $total + 0.0001) {
            throw new DomainException('Le total des voix dépasse le total des tantièmes de la copropriété.');
        }

        $adoptee = match ($resolution->majorite) {
            'simple' => $pour > $contre,
            'absolue' => $pour > $total / 2,
            'double' => $pour >= $total * 2 / 3,
            'unanimite' => abs($pour - $total) < 0.0001,
            default => $pour > $contre,
        };

        $resolution->update(['voix_pour' => $pour, 'voix_contre' => $contre, 'voix_abstention' => $abstention, 'adoptee' => $adoptee]);

        return $resolution;
    }

    public function convoquer(Assemblee $assemblee): void
    {
        if ($assemblee->statut !== StatutAssemblee::Planifiee) {
            throw new DomainException('Seule une assemblée planifiée peut être convoquée.');
        }

        $assemblee->update(['statut' => StatutAssemblee::Convoquee, 'convoquee_le' => now()]);
    }

    /**
     * Situation d'un copropriétaire : appelé, payé, reste dû.
     *
     * @return array{appele: string, paye: string, reste: string}
     */
    public function situation(Copropriete $copropriete, Tiers $coproprietaire): array
    {
        $lignes = \App\Models\AppelLigne::where('coproprietaire_id', $coproprietaire->id)
            ->where('statut', '!=', StatutPaiement::Annule->value)
            ->whereHas('appel', fn ($q) => $q->where('copropriete_id', $copropriete->id));

        $appele = Montant::normaliser((clone $lignes)->sum('montant'));
        $paye = Montant::normaliser((clone $lignes)->sum('montant_paye'));

        return ['appele' => $appele, 'paye' => $paye, 'reste' => Montant::sub($appele, $paye)];
    }
}
