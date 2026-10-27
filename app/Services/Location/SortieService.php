<?php

namespace App\Services\Location;

use App\Enums\NatureLoyer;
use App\Enums\StatutBail;
use App\Enums\StatutPaiement;
use App\Models\Bail;
use App\Models\DecompteLigne;
use App\Models\DecompteSortie;
use App\Models\Depense;
use App\Models\Loyer;
use App\Models\User;
use App\Services\Compta\ComptaService;
use App\Services\Finance\EncaissementService;
use App\Services\Ged\GedService;
use App\Services\Integration\NotificationService;
use App\Services\Numerotation;
use App\Services\Parametres;
use App\Support\Montant;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sortie du locataire : décompte des garanties après l'état des lieux de sortie.
 *
 *   crédits (dus au locataire) = dépôt de garantie + cautions SODECI / CIE versés + crédit de compteur CIE remboursé
 *   retenues (dues par le locataire) = impayés + réparations + dernières factures SODECI / CIE non réglées + autres
 *
 * À la validation : les garanties sont libérées (165 → 4191 « avoir du locataire »), le crédit de compteur est passé
 * en dépense (à la charge du propriétaire — déduite de son reversement — ou de l'agence), les retenues deviennent des dettes
 * et sont réglées par compensation sur l'avoir (encaissement « AVOIR »). Le solde est restitué au locataire,
 * ou reste dû et suivi par le recouvrement. Chaque option est paramétrable (Paramètres métier › Sortie du locataire).
 */
class SortieService
{
    protected const GARANTIES = [
        'depot_garantie' => NatureLoyer::DepotGarantie, 'caution_sodeci' => NatureLoyer::CautionSodeci, 'caution_cie' => NatureLoyer::CautionCie,
    ];

    protected const RETENUES = ['reparation', 'facture_sodeci', 'facture_cie', 'autre_retenue'];

    public function __construct(protected Parametres $parametres, protected Numerotation $numerotation, protected EncaissementService $caisse)
    {
    }

    /**
     * Décompte prérempli : garanties réellement versées, réparations de l'état des lieux de sortie, impayés,
     * lignes compteurs à compléter avec les justificatifs.
     */
    public function preparer(Bail $bail, ?Carbon $dateSortie = null): DecompteSortie
    {
        if ($bail->statut !== StatutBail::Resilie) {
            throw new DomainException('Résiliez d\'abord le bail (date de sortie du locataire).');
        }
        if ($existant = $bail->decomptes()->first()) {
            return $existant;
        }

        return DB::transaction(function () use ($bail, $dateSortie) {
            $edl = $bail->etatsLieux()->where('type', 'sortie')->latest('date')->latest('id')->first();
            $decompte = DecompteSortie::create([
                'bail_id' => $bail->id, 'tiers_id' => $bail->locataire_id, 'etat_lieux_id' => $edl?->id,
                'date_sortie' => $dateSortie ?? $bail->date_resiliation ?? today(),
            ]);
            $ordre = 0;
            $ajouter = fn (string $type, string $libelle, $montant) => $decompte->lignes()->create([
                'agence_id' => $decompte->agence_id, 'type' => $type, 'sens' => DecompteSortie::TYPES[$type][1],
                'libelle' => $libelle, 'montant' => Montant::normaliser($montant), 'ordre' => $ordre++,
            ]);

            foreach ($this->garantiesVersees($bail) as $type => $montant) {
                $ajouter($type, DecompteSortie::TYPES[$type][0], $montant);
            }
            if ($this->parametres->get('sortie.credit_compteur_cie') && $bail->compteur_cie_credit) {
                $ajouter('credit_compteur_cie', 'Crédit de compteur CIE remboursé (à justifier par les reçus de rechargement)', 0);
            }
            if ($this->parametres->get('sortie.deduire_impayes')) {
                $impayes = $this->impayes($bail)->sum(fn ($l) => (float) $l->resteAPayer());
                if ($impayes > 0) {
                    $ajouter('impaye', DecompteSortie::TYPES['impaye'][0], $impayes);
                }
            }
            if ($this->parametres->get('sortie.deduire_reparations') && $edl) {
                foreach ($edl->reparations ?? [] as $r) {
                    if (($r['a_charge'] ?? 'locataire') === 'locataire' && (float) ($r['montant'] ?? 0) > 0) {
                        $ajouter('reparation', 'Réparation : ' . $r['libelle'], $r['montant']);
                    }
                }
            }
            if ($this->parametres->get('sortie.cautions_compteurs')) {
                if (Montant::estPositif($bail->caution_sodeci)) {
                    $ajouter('facture_sodeci', 'Dernière facture SODECI non réglée (0 si réglée, joindre la preuve)', 0);
                }
                if (Montant::estPositif($bail->caution_cie)) {
                    $ajouter('facture_cie', 'Dernière facture CIE non réglée (0 si réglée, joindre la preuve)', 0);
                }
            }
            $this->totaliser($decompte);

            return $decompte;
        });
    }

    public function ajouterLigne(DecompteSortie $decompte, string $type, string $libelle, $montant): DecompteLigne
    {
        $this->modifiable($decompte);
        $ligne = $decompte->lignes()->create(['agence_id' => $decompte->agence_id, 'type' => $type, 'sens' => DecompteSortie::TYPES[$type][1],
            'libelle' => $libelle, 'montant' => Montant::normaliser($montant), 'ordre' => (int) $decompte->lignes()->max('ordre') + 1]);
        $this->totaliser($decompte);

        return $ligne;
    }

    public function modifierLigne(DecompteLigne $ligne, string $libelle, $montant): void
    {
        $this->modifiable($ligne->decompte);
        if (array_key_exists($ligne->type, self::GARANTIES) || $ligne->type === 'impaye') {
            throw new DomainException('Les garanties versées et les impayés sont calculés par l\'application.');
        }
        $ligne->update(['libelle' => $libelle, 'montant' => Montant::normaliser($montant)]);
        $this->totaliser($ligne->decompte);
    }

    public function supprimerLigne(DecompteLigne $ligne): void
    {
        $this->modifiable($ligne->decompte);
        if (array_key_exists($ligne->type, self::GARANTIES)) {
            throw new DomainException('Une garantie versée ne peut pas être retirée du décompte.');
        }
        $decompte = $ligne->decompte;
        $ligne->delete();
        $this->totaliser($decompte);
    }

    public function joindre(DecompteLigne $ligne, UploadedFile $fichier): void
    {
        app(GedService::class)->televerser($fichier, [
            'titre' => 'Justificatif — ' . $ligne->libelle,
            'categorie' => match ($ligne->type) { 'credit_compteur_cie', 'facture_cie' => 'FACTURE_CIE', 'facture_sodeci' => 'FACTURE_SODECI', default => 'JUSTIFICATIF' },
        ], $ligne);
    }

    /**
     * Validation : garanties libérées, retenues constituées en dettes et compensées, crédit compteur en dépense.
     */
    public function valider(DecompteSortie $decompte, ?User $auteur = null): DecompteSortie
    {
        $this->modifiable($decompte);
        $decompte->load('lignes.justificatifs', 'bail.bien');
        if ($this->parametres->get('sortie.preuve_obligatoire')) {
            foreach ($decompte->lignes as $ligne) {
                $exige = $ligne->exigeJustificatif() && ($ligne->type !== 'credit_compteur_cie' || Montant::estPositif($ligne->montant));
                if ($exige && $ligne->justificatifs->isEmpty()) {
                    throw new DomainException('Justificatif manquant : « ' . $ligne->libelle . ' » (reçus de rechargement CIE, dernière facture ou preuve de paiement).');
                }
            }
        }

        return DB::transaction(function () use ($decompte, $auteur) {
            $bail = $decompte->bail;
            $this->actualiserImpayes($decompte);
            $decompte->refresh()->load('lignes');

            // retenues → dettes du locataire (réparations, factures…), échues à la date de sortie
            foreach ($decompte->lignes->whereIn('type', self::RETENUES) as $ligne) {
                if (Montant::estPositif($ligne->montant)) {
                    $loyer = Loyer::create(['agence_id' => $bail->agence_id, 'bail_id' => $bail->id, 'nature' => NatureLoyer::Retenue, 'libelle' => $ligne->libelle,
                        'date_echeance' => $decompte->date_sortie, 'montant' => $ligne->montant, 'statut' => StatutPaiement::APayer]);
                    $ligne->update(['loyer_id' => $loyer->id]);
                }
            }
            $this->annulerGarantiesNonVersees($bail);

            $garanties = (string) $decompte->lignes->filter(fn ($l) => array_key_exists($l->type, self::GARANTIES))->sum('montant');
            $compteur = (string) $decompte->lignes->where('type', 'credit_compteur_cie')->sum('montant');
            $autresCredits = (string) $decompte->lignes->where('type', 'autre_credit')->sum('montant');
            $credits = Montant::add(Montant::add($garanties, $compteur), $autresCredits);

            // garanties libérées : 165 (dépôts reçus) → 4191 (avoir du locataire sortant)
            $compta = app(ComptaService::class);
            if (Montant::estPositif(Montant::add($garanties, $autresCredits))) {
                $compta->tenter(fn () => $compta->passer('OD', $decompte->date_sortie, 'Sortie locataire — libération des garanties ' . $bail->numero, [
                    ['compte' => '165', 'debit' => $garanties, 'libelle' => 'Garanties du bail ' . $bail->numero, 'tiers_id' => $decompte->tiers_id],
                    // autres sommes accordées au locataire (geste commercial, trop-perçu…) : charge de l'agence
                    ['compte' => $compta->regle('DEPENSE', 'AUTRE', '6588')[0], 'debit' => $autresCredits, 'libelle' => 'Autres sommes dues au locataire'],
                    ['compte' => '4191', 'credit' => Montant::add($garanties, $autresCredits), 'libelle' => 'Avoir du locataire sortant', 'tiers_id' => $decompte->tiers_id],
                ], $decompte, null, operation: 'SORTIE'));
            }

            // crédit de compteur CIE : charge du propriétaire (déduite de son reversement) ou de l'agence
            if (Montant::estPositif($compteur)) {
                $proprietaire = $this->parametres->get('sortie.credit_compteur_charge') === 'proprietaire' && $bail->bien?->proprietaire_id;
                $depense = Depense::create([
                    'numero' => $this->numerotation->suivant('depense'), 'date_depense' => $decompte->date_sortie, 'categorie' => 'COMPTEUR_CIE',
                    'libelle' => 'Crédit compteur CIE remboursé au locataire — bail ' . $bail->numero, 'montant' => $compteur,
                    'beneficiaire_id' => $decompte->tiers_id, 'bien_id' => $bail->bien_id, 'refacturable_proprietaire' => (bool) $proprietaire,
                    'mode_paiement' => 'AVOIR', 'statut' => 'payee', 'created_by' => $auteur?->id ?? auth()->id(),
                ]);
                $decompte->depense_compteur_id = $depense->id;
            }

            // compensation des dettes sur l'avoir
            $dettes = $this->dettesACompenser($bail);
            $totalDettes = (string) $dettes->sum(fn ($l) => (float) $l->resteAPayer());
            $compense = Montant::min($credits, $totalDettes);
            if (Montant::estPositif($compense)) {
                $encaissement = $this->caisse->encaisser(['tiers_id' => $decompte->tiers_id, 'mode_paiement' => 'AVOIR', 'date_encaissement' => $decompte->date_sortie,
                    'observation' => 'Compensation sur les garanties — décompte de sortie du bail ' . $bail->numero], $this->caisse->repartir($dettes, $compense), $auteur);
                $decompte->encaissement_id = $encaissement->id;
            }

            $aRembourser = Montant::sub($credits, $compense);
            $resteDu = Montant::sub($totalDettes, $compense);
            $decompte->fill([
                'numero' => $this->numerotation->suivant('decompte'), 'total_credits' => $credits, 'total_retenues' => $totalDettes,
                'montant_compense' => $compense, 'montant_a_rembourser' => $aRembourser, 'reste_du' => $resteDu,
                'statut' => Montant::estPositif($aRembourser) ? 'valide' : (Montant::estPositif($resteDu) ? 'reste_du' : 'solde'),
                'valide_le' => now(), 'valide_par' => $auteur?->id ?? auth()->id(),
            ])->save();
            $bail->update(['depot_restitue' => $aRembourser]);

            $this->notifier($decompte, 'DECOMPTE_SORTIE');

            return $decompte;
        });
    }

    /**
     * Restitution au locataire du solde du décompte, avec la preuve du paiement (reçu signé, capture du virement…).
     */
    public function restituer(DecompteSortie $decompte, array $donnees, ?UploadedFile $preuve = null, ?User $auteur = null): DecompteSortie
    {
        if ($decompte->statut !== 'valide') {
            throw new DomainException('Aucune somme à restituer sur ce décompte.');
        }

        return DB::transaction(function () use ($decompte, $donnees, $preuve, $auteur) {
            $depense = Depense::create([
                'numero' => $this->numerotation->suivant('depense'), 'date_depense' => $donnees['date'] ?? today(), 'categorie' => 'RESTITUTION',
                'libelle' => 'Restitution des garanties — décompte ' . $decompte->numero, 'montant' => $decompte->montant_a_rembourser,
                'beneficiaire_id' => $decompte->tiers_id, 'bien_id' => $decompte->bail->bien_id, 'mode_paiement' => $donnees['mode_paiement'],
                'reference' => $donnees['reference'] ?? null, 'statut' => 'payee', 'created_by' => $auteur?->id ?? auth()->id(),
            ]);
            $decompte->update(['depense_id' => $depense->id, 'rembourse_le' => $donnees['date'] ?? today(), 'statut' => 'rembourse']);

            if ($preuve) {
                app(GedService::class)->televerser($preuve, ['titre' => 'Preuve de restitution — ' . $decompte->numero, 'categorie' => 'JUSTIFICATIF'], $decompte);
            }
            $this->notifier($decompte, 'RESTITUTION_GARANTIE');

            return $decompte;
        });
    }

    /** @return array<string, string> type => montant versé */
    public function garantiesVersees(Bail $bail): array
    {
        $types = $this->parametres->get('sortie.cautions_compteurs') ? self::GARANTIES : ['depot_garantie' => NatureLoyer::DepotGarantie];
        $versees = [];
        foreach ($types as $type => $nature) {
            $montant = (string) Loyer::where('bail_id', $bail->id)->where('nature', $nature->value)->sum('montant_paye');
            if (Montant::estPositif($montant)) {
                $versees[$type] = $montant;
            }
        }

        return $versees;
    }

    /** Loyers, charges, pénalités… restant dus (hors garanties). */
    public function impayes(Bail $bail): Collection
    {
        return Loyer::impayes()->where('bail_id', $bail->id)
            ->whereNotIn('nature', [NatureLoyer::DepotGarantie->value, NatureLoyer::CautionSodeci->value, NatureLoyer::CautionCie->value, NatureLoyer::Retenue->value])
            ->orderBy('date_echeance')->orderBy('id')->get();
    }

    public function totaliser(DecompteSortie $decompte): void
    {
        $lignes = $decompte->lignes()->get();
        $credits = (string) $lignes->where('sens', 'credit')->sum('montant');
        $retenues = (string) $lignes->where('sens', 'debit')->sum('montant');
        $solde = Montant::sub($credits, $retenues);
        $decompte->update(['total_credits' => $credits, 'total_retenues' => $retenues,
            'montant_a_rembourser' => Montant::estPositif($solde) ? $solde : '0.00', 'reste_du' => Montant::estPositif($solde) ? '0.00' : Montant::sub($retenues, $credits)]);
    }

    protected function actualiserImpayes(DecompteSortie $decompte): void
    {
        $decompte->lignes()->where('type', 'impaye')->delete();
        if ($this->parametres->get('sortie.deduire_impayes')) {
            $montant = (string) $this->impayes($decompte->bail)->sum(fn ($l) => (float) $l->resteAPayer());
            if (Montant::estPositif($montant)) {
                $decompte->lignes()->create(['agence_id' => $decompte->agence_id, 'type' => 'impaye', 'sens' => 'debit',
                    'libelle' => DecompteSortie::TYPES['impaye'][0], 'montant' => $montant, 'ordre' => 50]);
            }
        }
    }

    /** Dettes réglées par compensation : retenues de sortie, et impayés si le paramètre l'autorise. */
    protected function dettesACompenser(Bail $bail): Collection
    {
        $retenues = Loyer::impayes()->where('bail_id', $bail->id)->where('nature', NatureLoyer::Retenue->value)->orderBy('id')->get();

        return $this->parametres->get('sortie.deduire_impayes') ? $this->impayes($bail)->concat($retenues) : $retenues;
    }

    /** Garanties non versées : elles ne sont plus exigibles à la sortie. */
    protected function annulerGarantiesNonVersees(Bail $bail): void
    {
        foreach (Loyer::impayes()->where('bail_id', $bail->id)->whereIn('nature', array_map(fn ($n) => $n->value, self::GARANTIES))->get() as $loyer) {
            Montant::estPositif($loyer->montant_paye)
                ? $loyer->update(['montant' => $loyer->montant_paye, 'statut' => StatutPaiement::Paye])
                : $loyer->update(['statut' => StatutPaiement::Annule]);
        }
    }

    protected function notifier(DecompteSortie $decompte, string $modele): void
    {
        $tiers = $decompte->locataire;
        try {
            NotificationService::notifier($modele, ['email' => $tiers->email, 'telephone' => ($tiers->indicatif ?: '') . $tiers->telephone, 'nom' => $tiers->nom_complet], [
                'numero' => $decompte->numero, 'bail' => $decompte->bail->numero, 'garanties' => Montant::formater($decompte->total_credits),
                'retenues' => Montant::formater($decompte->total_retenues), 'montant' => Montant::formater($decompte->montant_a_rembourser),
                'reste_du' => Montant::formater($decompte->reste_du), 'date' => ($decompte->rembourse_le ?? $decompte->date_sortie)->format('d/m/Y'),
            ], $decompte);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function modifiable(DecompteSortie $decompte): void
    {
        if (!$decompte->estModifiable()) {
            throw new DomainException('Ce décompte est validé : il n\'est plus modifiable.');
        }
    }
}
