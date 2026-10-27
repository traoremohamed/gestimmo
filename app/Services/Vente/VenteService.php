<?php

namespace App\Services\Vente;

use App\Enums\RoleTiers;
use App\Enums\StatutBien;
use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Enums\StatutVente;
use App\Models\Bien;
use App\Models\Depense;
use App\Models\Echeance;
use App\Models\FraisType;
use App\Models\Reservation;
use App\Models\Tiers;
use App\Models\User;
use App\Models\Vente;
use App\Models\VenteFrais;
use App\Services\Numerotation;
use App\Services\Parametres;
use App\Support\Montant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'un contrat de vente :
 * brouillon (biens, frais, conditions) -> validation (numéro, échéancier, biens vendus)
 * -> encaissements -> soldée, ou désistement -> remboursement.
 *
 * Remplace DossierController::montage / caisse / desistement / remboursement :
 * tout est transactionnel et les montants sont recalculés côté serveur.
 */
class VenteService
{
    public function __construct(
        protected Numerotation $numerotation,
        protected Parametres $parametres,
        protected ReservationService $reservations,
    ) {
    }

    public function creer(Tiers $acquereur, ?User $commercial = null, array $donnees = []): Vente
    {
        return DB::transaction(function () use ($acquereur, $commercial, $donnees) {
            $vente = Vente::create(array_merge([
                'acquereur_id' => $acquereur->id,
                'commercial_id' => $commercial?->id,
                'statut' => StatutVente::Brouillon,
                'date_contrat' => today(),
                'mode_financement' => 'echelonne',
                'taux_acompte' => $this->parametres->get('vente.taux_acompte'),
                'nombre_echeances' => $this->parametres->get('vente.nombre_echeances'),
                'periodicite_mois' => $this->parametres->get('vente.periodicite_mois'),
                'date_premiere_echeance' => today()->addMonthNoOverflow()->startOfMonth(),
                'taux_commission' => $this->parametres->get('vente.taux_commission'),
                'created_by' => auth()->id(),
            ], $donnees));

            foreach (FraisType::activite('vente')->where('obligatoire', true)->get() as $fraisType) {
                $this->ajouterFrais($vente, $fraisType);
            }

            return $vente->refresh();
        });
    }

    public function creerDepuisReservation(Reservation $reservation, ?User $commercial = null): Vente
    {
        if (!$reservation->estActive()) {
            throw new DomainException('La réservation n\'est plus active.');
        }

        return DB::transaction(function () use ($reservation, $commercial) {
            $vente = $this->creer($reservation->tiers, $commercial ?? $reservation->commercial);
            $this->ajouterBien($vente, $reservation->bien);
            $reservation->update(['vente_id' => $vente->id]);

            return $vente;
        });
    }

    public function ajouterBien(Vente $vente, Bien $bien, $prix = null): void
    {
        $this->exigerBrouillon($vente);

        if (!$bien->a_vendre) {
            throw new DomainException("Le bien {$bien->reference} n'est pas proposé à la vente.");
        }

        $reserveParAcquereur = $bien->statut === StatutBien::Reserve && $this->reservations->estReservePar($bien, $vente->acquereur);
        if (!$bien->estDisponible() && !$reserveParAcquereur) {
            throw new DomainException("Le bien {$bien->reference} n'est pas disponible ({$bien->statut->libelle()}).");
        }

        if ($vente->lignes()->where('bien_id', $bien->id)->exists()) {
            throw new DomainException("Le bien {$bien->reference} est déjà dans ce contrat.");
        }

        $prix = Montant::normaliser($prix ?? $bien->prix_vente);
        if (!Montant::estPositif($prix)) {
            throw new DomainException("Le prix du bien {$bien->reference} n'est pas renseigné.");
        }

        $vente->lignes()->create(['bien_id' => $bien->id, 'prix' => $prix]);
        $this->recalculer($vente);
    }

    public function retirerBien(Vente $vente, Bien $bien): void
    {
        $this->exigerBrouillon($vente);
        $vente->lignes()->where('bien_id', $bien->id)->delete();
        $this->recalculer($vente);
    }

    public function ajouterFrais(Vente $vente, FraisType $fraisType, $montant = null): VenteFrais
    {
        $this->exigerBrouillon($vente);

        $frais = $vente->frais()->create([
            'frais_type_id' => $fraisType->id,
            'libelle' => $fraisType->libelle,
            'montant' => Montant::normaliser($montant ?? $fraisType->montantPour($vente->montant_net ?: 0)),
            'statut' => StatutPaiement::APayer,
            'document_requis' => $fraisType->document_requis,
            'statut_document' => $fraisType->document_requis ? 'attendu' : null,
        ]);
        $this->recalculer($vente);

        return $frais;
    }

    public function retirerFrais(VenteFrais $frais): void
    {
        $vente = $frais->vente;
        $this->exigerBrouillon($vente);
        $frais->delete();
        $this->recalculer($vente);
    }

    /**
     * Conditions financières : remise, mode de financement, acompte, nombre et périodicité des échéances.
     */
    public function definirConditions(Vente $vente, array $conditions): void
    {
        $this->exigerBrouillon($vente);

        $vente->fill(array_intersect_key($conditions, array_flip([
            'remise', 'mode_financement', 'taux_acompte', 'montant_acompte', 'nombre_echeances', 'periodicite_mois',
            'date_premiere_echeance', 'date_contrat', 'taux_commission', 'commercial_id', 'observation',
        ])));

        // un acompte saisi en montant prime sur le taux
        if (isset($conditions['montant_acompte']) && $conditions['montant_acompte'] !== '' && $conditions['montant_acompte'] !== null) {
            $vente->taux_acompte = 0;
        }

        $vente->save();
        $this->recalculer($vente);
    }

    public function recalculer(Vente $vente): void
    {
        $prixTotal = Montant::normaliser($vente->lignes()->sum('prix'));
        $net = Montant::sub($prixTotal, $vente->remise ?? 0);

        if (Montant::compare($net, 0) < 0) {
            throw new DomainException('La remise ne peut pas dépasser le prix total.');
        }

        $acompte = (float) $vente->taux_acompte > 0
            ? Montant::pourcentage($net, $vente->taux_acompte)
            : Montant::min(Montant::normaliser($vente->montant_acompte), $net);

        $vente->forceFill([
            'prix_total' => $prixTotal,
            'montant_net' => $net,
            'montant_acompte' => $vente->mode_financement === 'comptant' ? $net : $acompte,
            'montant_frais' => Montant::normaliser($vente->frais()->where('statut', '!=', StatutPaiement::Annule->value)->sum('montant')),
            'montant_commission' => Montant::pourcentage($net, $vente->taux_commission ?? 0),
        ])->save();
    }

    /**
     * Échéancier prévisionnel (affiché avant validation, puis enregistré à la validation).
     *
     * @return array<int, array{numero: int, libelle: string, date_echeance: Carbon, montant: string}>
     */
    public function simulerEcheancier(Vente $vente): array
    {
        $net = Montant::normaliser($vente->montant_net);
        $dateContrat = Carbon::parse($vente->date_contrat ?? today());

        if (!Montant::estPositif($net)) {
            return [];
        }

        if ($vente->mode_financement === 'comptant') {
            return [['numero' => 1, 'libelle' => 'Paiement comptant', 'date_echeance' => $dateContrat, 'montant' => $net]];
        }

        $lignes = [];
        $acompte = Montant::normaliser($vente->montant_acompte);
        if (Montant::estPositif($acompte)) {
            $lignes[] = ['numero' => 0, 'libelle' => 'Acompte', 'date_echeance' => $dateContrat, 'montant' => $acompte];
        }

        $reste = Montant::sub($net, $acompte);
        $nombre = max(1, (int) $vente->nombre_echeances);
        $periodicite = max(1, (int) $vente->periodicite_mois);
        $premiere = Carbon::parse($vente->date_premiere_echeance ?? $dateContrat->copy()->addMonthNoOverflow());

        if (Montant::estPositif($reste)) {
            foreach (Montant::repartirEgal($reste, $nombre) as $i => $montant) {
                $lignes[] = [
                    'numero' => $i + 1,
                    'libelle' => 'Échéance ' . ($i + 1) . '/' . $nombre,
                    'date_echeance' => $premiere->copy()->addMonthsNoOverflow($i * $periodicite),
                    'montant' => $montant,
                ];
            }
        }

        return $lignes;
    }

    public function valider(Vente $vente, ?User $valideur = null): Vente
    {
        $this->exigerBrouillon($vente);

        return DB::transaction(function () use ($vente, $valideur) {
            $biens = Bien::whereIn('id', $vente->lignes()->pluck('bien_id'))->lockForUpdate()->get();

            if ($biens->isEmpty()) {
                throw new DomainException('Ajoutez au moins un bien avant de valider le contrat.');
            }

            foreach ($biens as $bien) {
                $reserveParAcquereur = $bien->statut === StatutBien::Reserve && $this->reservations->estReservePar($bien, $vente->acquereur);
                if (!$bien->estDisponible() && !$reserveParAcquereur) {
                    throw new DomainException("Le bien {$bien->reference} n'est plus disponible.");
                }
            }

            $this->recalculer($vente);

            foreach ($this->simulerEcheancier($vente) as $ligne) {
                $vente->echeances()->create($ligne + ['statut' => StatutPaiement::APayer]);
            }

            Bien::whereIn('id', $biens->pluck('id'))->update(['statut' => StatutBien::Vendu->value]);

            Reservation::whereIn('bien_id', $biens->pluck('id'))
                ->where('tiers_id', $vente->acquereur_id)
                ->where('statut', StatutReservation::Active->value)
                ->update(['statut' => StatutReservation::Convertie->value, 'vente_id' => $vente->id]);

            $vente->forceFill([
                'numero' => $this->numerotation->suivant('vente'),
                'statut' => StatutVente::EnCours,
                'valide_par' => ($valideur ?? auth()->user())?->id,
                'valide_le' => now(),
            ])->save();

            $vente->acquereur->ajouterRole(RoleTiers::Acquereur);

            return $vente;
        });
    }

    /**
     * Désistement : les échéances et frais non réglés sont annulés, les biens redeviennent
     * disponibles, une retenue est appliquée sur les sommes versées au titre du prix.
     */
    public function desister(Vente $vente, string $motif, ?string $observation = null, $tauxRetenue = null): Vente
    {
        if ($vente->statut !== StatutVente::EnCours) {
            throw new DomainException('Seul un contrat en cours peut faire l\'objet d\'un désistement.');
        }

        return DB::transaction(function () use ($vente, $motif, $observation, $tauxRetenue) {
            $tauxRetenue ??= $this->parametres->get('vente.taux_retenue_desistement');
            $payePrix = $vente->totalPayePrix();
            $retenue = Montant::pourcentage($payePrix, $tauxRetenue);

            // le solde restant dû est abandonné ; les montants déjà versés restent tracés
            $vente->echeances()->impayes()->update(['statut' => StatutPaiement::Annule->value]);
            $vente->frais()->impayes()->update(['statut' => StatutPaiement::Annule->value]);

            Bien::whereIn('id', $vente->lignes()->pluck('bien_id'))->update(['statut' => StatutBien::Disponible->value]);

            $vente->forceFill([
                'statut' => StatutVente::Desistee,
                'date_desistement' => today(),
                'motif_desistement' => $motif,
                'observation_desistement' => $observation,
                'montant_retenu' => $retenue,
                'montant_a_rembourser' => Montant::sub($payePrix, $retenue),
            ])->save();

            return $vente;
        });
    }

    /**
     * Remboursement du client désisté : enregistré comme une dépense (décaissement).
     */
    public function rembourser(Vente $vente, string $modePaiement, ?string $reference = null, ?Carbon $date = null): Depense
    {
        if ($vente->statut !== StatutVente::Desistee || $vente->rembourse_le !== null) {
            throw new DomainException('Ce contrat n\'est pas en attente de remboursement.');
        }
        if (!Montant::estPositif($vente->montant_a_rembourser)) {
            throw new DomainException('Aucun montant à rembourser.');
        }

        return DB::transaction(function () use ($vente, $modePaiement, $reference, $date) {
            $depense = Depense::create([
                'numero' => $this->numerotation->suivant('depense'),
                'date_depense' => $date ?? today(),
                'categorie' => 'REMBOURSEMENT',
                'libelle' => 'Remboursement suite au désistement du contrat ' . $vente->numero,
                'montant' => $vente->montant_a_rembourser,
                'beneficiaire_id' => $vente->acquereur_id,
                'mode_paiement' => $modePaiement,
                'reference' => $reference,
                'statut' => 'payee',
                'created_by' => auth()->id(),
            ]);

            $vente->forceFill(['rembourse_le' => now(), 'depense_remboursement_id' => $depense->id])->save();

            return $depense;
        });
    }

    /**
     * Appelé après chaque encaissement / annulation sur une échéance ou un frais du contrat.
     */
    public function majStatut(Vente $vente): void
    {
        if (!in_array($vente->statut, [StatutVente::EnCours, StatutVente::Soldee], true)) {
            return;
        }

        $resteDettes = Echeance::where('vente_id', $vente->id)->impayes()->exists()
            || VenteFrais::where('vente_id', $vente->id)->impayes()->exists();

        $vente->update(['statut' => $resteDettes ? StatutVente::EnCours : StatutVente::Soldee]);
    }

    public function supprimerBrouillon(Vente $vente): void
    {
        $this->exigerBrouillon($vente);

        DB::transaction(function () use ($vente) {
            Reservation::where('vente_id', $vente->id)->update(['vente_id' => null]);
            $vente->frais()->delete();
            $vente->lignes()->delete();
            $vente->forceDelete();
        });
    }

    protected function exigerBrouillon(Vente $vente): void
    {
        if (!$vente->estModifiable()) {
            throw new DomainException('Le contrat est validé : il ne peut plus être modifié.');
        }
    }
}
