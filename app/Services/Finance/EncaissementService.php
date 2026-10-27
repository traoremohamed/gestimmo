<?php

namespace App\Services\Finance;

use App\Enums\StatutAppelFonds;
use App\Enums\StatutPaiement;
use App\Models\AppelFonds;
use App\Models\AppelLigne;
use App\Models\Echeance;
use App\Models\Encaissement;
use App\Models\Loyer;
use App\Models\Concerns\Payable;
use App\Models\Sejour;
use App\Models\Tiers;
use App\Models\User;
use App\Models\VenteFrais;
use App\Services\Compta\ComptaService;
use App\Services\Integration\NotificationService;
use App\Services\Numerotation;
use App\Services\Vente\VenteService;
use App\Support\Montant;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Encaissements : un reçu numéroté peut solder plusieurs dettes (échéances, frais,
 * loyers, appels de fonds, séjours). Le montant affecté à chaque dette est contrôlé
 * côté serveur et ne peut jamais dépasser son reste à payer.
 */
class EncaissementService
{
    public function __construct(protected Numerotation $numerotation, protected VenteService $ventes)
    {
    }

    /**
     * Dettes ouvertes d'un tiers, toutes activités confondues, de la plus ancienne à la plus récente.
     *
     * @return Collection<int, Payable&Model>
     */
    public function dettesOuvertes(Tiers $tiers, ?string $activite = null): Collection
    {
        $dettes = collect();

        if (!$activite || $activite === 'vente') {
            $dettes = $dettes
                ->merge(VenteFrais::impayes()->with('vente')->whereHas('vente', fn ($q) => $q->where('acquereur_id', $tiers->id)->where('statut', 'en_cours'))->get())
                ->merge(Echeance::impayes()->with('vente')->whereHas('vente', fn ($q) => $q->where('acquereur_id', $tiers->id)->where('statut', 'en_cours'))->get());
        }
        if (!$activite || $activite === 'location') {
            $dettes = $dettes->merge(Loyer::impayes()->with('bail')->whereHas('bail', fn ($q) => $q->where('locataire_id', $tiers->id))->get());
        }
        if (!$activite || $activite === 'syndic') {
            $dettes = $dettes->merge(AppelLigne::impayes()->with('appel', 'lot')->where('coproprietaire_id', $tiers->id)
                ->whereHas('appel', fn ($q) => $q->where('statut', StatutAppelFonds::Emis->value))->get());
        }
        if (!$activite || $activite === 'meuble') {
            $dettes = $dettes->merge(Sejour::impayes()->where('tiers_id', $tiers->id)->get());
        }

        return $dettes->sortBy(fn ($dette) => $this->dateDette($dette)?->timestamp ?? 0)->values();
    }

    /**
     * Répartit un montant global sur les dettes, de la plus ancienne à la plus récente.
     *
     * @param  iterable<Payable>  $dettes
     * @return array<int, array{0: Payable, 1: string}>
     */
    public function repartir(iterable $dettes, $montant): array
    {
        $restant = Montant::normaliser($montant);
        $affectations = [];

        foreach ($dettes as $dette) {
            if (!Montant::estPositif($restant)) {
                break;
            }
            $part = Montant::min($restant, $dette->resteAPayer());
            if (Montant::estPositif($part)) {
                $affectations[] = [$dette, $part];
                $restant = Montant::sub($restant, $part);
            }
        }

        if (Montant::estPositif($restant)) {
            throw new DomainException('Le montant saisi dépasse le total dû de ' . Montant::formater($restant) . '.');
        }

        return $affectations;
    }

    /**
     * @param  array{tiers_id?: int, date_encaissement?: string, mode_paiement: string, reference?: string, banque?: string, observation?: string}  $donnees
     * @param  array<int, array{0: Payable&Model, 1: string|float|int}>  $affectations  [dette, montant]
     */
    public function encaisser(array $donnees, array $affectations, ?User $caissier = null): Encaissement
    {
        if (empty($affectations)) {
            throw new DomainException('Sélectionnez au moins une ligne à encaisser.');
        }

        return DB::transaction(function () use ($donnees, $affectations, $caissier) {
            $total = '0.00';
            $verrouillees = [];
            $activites = [];

            foreach ($affectations as [$dette, $montant]) {
                $montant = Montant::normaliser($montant);
                if (!Montant::estPositif($montant)) {
                    continue;
                }

                /** @var Payable&Model $dette */
                $dette = $dette->newQuery()->whereKey($dette->getKey())->lockForUpdate()->firstOrFail();

                if (($dette->statut ?? null) === StatutPaiement::Annule) {
                    throw new DomainException('La ligne « ' . $dette->libelleAffectation() . ' » est annulée.');
                }
                if (Montant::compare($montant, $dette->resteAPayer()) > 0) {
                    throw new DomainException('Le montant affecté à « ' . $dette->libelleAffectation() . ' » dépasse son reste à payer ('
                        . Montant::formater($dette->resteAPayer()) . ').');
                }

                $verrouillees[] = [$dette, $montant];
                $activites[$dette->activite()] = true;
                $total = Montant::add($total, $montant);
            }

            if (!Montant::estPositif($total)) {
                throw new DomainException('Le montant à encaisser doit être supérieur à zéro.');
            }

            $encaissement = Encaissement::create([
                'numero' => $this->numerotation->suivant('encaissement'),
                'tiers_id' => $donnees['tiers_id'] ?? null,
                'date_encaissement' => $donnees['date_encaissement'] ?? today(),
                'montant' => $total,
                'mode_paiement' => $donnees['mode_paiement'],
                'reference' => $donnees['reference'] ?? null,
                'banque' => $donnees['banque'] ?? null,
                'observation' => $donnees['observation'] ?? null,
                'activite' => count($activites) === 1 ? array_key_first($activites) : 'autre',
                'statut' => 'valide',
                'caissier_id' => ($caissier ?? auth()->user())?->id,
            ]);

            foreach ($verrouillees as [$dette, $montant]) {
                $encaissement->affectations()->create([
                    'affectable_type' => $dette->getMorphClass(),
                    'affectable_id' => $dette->getKey(),
                    'montant' => $montant,
                ]);
                $dette->imputer($montant);
                $this->apresMouvement($dette);
            }

            app(ComptaService::class)->tenter(fn () => app(ComptaService::class)->encaissement($encaissement));
            $this->notifier($encaissement, $verrouillees);

            return $encaissement->load('affectations.affectable');
        });
    }

    /**
     * Annulation (jamais de suppression) : chaque affectation est contre-passée.
     */
    public function annuler(Encaissement $encaissement, string $motif, ?User $auteur = null): void
    {
        if ($encaissement->estAnnule()) {
            throw new DomainException('Cet encaissement est déjà annulé.');
        }

        DB::transaction(function () use ($encaissement, $motif, $auteur) {
            foreach ($encaissement->affectations()->with('affectable')->get() as $affectation) {
                $dette = $affectation->affectable;
                if (!$dette) {
                    continue;
                }
                $dette = $dette->newQuery()->whereKey($dette->getKey())->lockForUpdate()->first();
                $dette->desimputer(Montant::normaliser($affectation->montant));
                $this->apresMouvement($dette);
            }

            $encaissement->update([
                'statut' => 'annule',
                'motif_annulation' => $motif,
                'annule_par' => ($auteur ?? auth()->user())?->id,
                'annule_le' => now(),
            ]);

            app(ComptaService::class)->tenter(fn () => app(ComptaService::class)->annulerPiece($encaissement, $motif));
        });
    }

    /**
     * Effets de bord d'un paiement selon le type de dette.
     */
    protected function apresMouvement(Model $dette): void
    {
        match (true) {
            $dette instanceof Echeance, $dette instanceof VenteFrais => $this->ventes->majStatut($dette->vente),
            $dette instanceof Loyer => $this->majQuittance($dette),
            $dette instanceof AppelLigne => $this->majAppel($dette->appel),
            default => null,
        };
    }

    /**
     * Reçu au payeur et quittance des loyers soldés (modèles RECU_PAIEMENT / QUITTANCE_LOYER), envoyés après validation.
     * Une notification ne doit jamais bloquer l'encaissement.
     */
    protected function notifier(Encaissement $encaissement, array $affectations): void
    {
        $tiers = $encaissement->tiers;
        // compensation sur les garanties : le locataire reçoit son décompte de sortie, pas un reçu
        if (!$tiers || $encaissement->mode_paiement === 'AVOIR') {
            return;
        }
        $destinataire = ['email' => $tiers->email, 'telephone' => ($tiers->indicatif ?: '') . $tiers->telephone, 'nom' => $tiers->nom_complet];

        try {
            $recu = $this->pdfSigne('pdf.recu', ['encaissement' => $encaissement->load(['tiers', 'caissier', 'affectations.affectable'])],
                'recu-' . $encaissement->numero, $encaissement, 'RECU', $encaissement->statut);
            NotificationService::notifier('RECU_PAIEMENT', $destinataire, [
                'numero' => $encaissement->numero, 'montant' => Montant::formater($encaissement->montant), 'date' => $encaissement->date_encaissement->format('d/m/Y'),
                'mode' => $encaissement->mode_paiement,
                'details' => collect($affectations)->map(fn ($a) => '- ' . $a[0]->libelleAffectation() . ' : ' . Montant::formater($a[1]))->implode("\n"),
            ], $encaissement, [$recu]);

            foreach ($affectations as [$dette]) {
                if ($dette instanceof Loyer && $dette->quittance_numero && $dette->wasChanged('quittance_numero')) {
                    $quittance = $this->pdfSigne('pdf.quittance', ['loyer' => $dette->load(['bail.locataire', 'bail.bien.proprietaire', 'affectations.encaissement'])],
                        'quittance-' . $dette->quittance_numero, $dette, 'QUITTANCE', $dette->quittance_numero);
                    NotificationService::notifier('QUITTANCE_LOYER', $destinataire, [
                        'periode' => $dette->libelle, 'bien' => $dette->bail?->bien?->libelle, 'montant' => Montant::formater($dette->montant), 'numero' => $dette->quittance_numero,
                    ], $dette, [$quittance]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Document signé QR et archivé dans la GED, joint à l'e-mail ; null si la génération échoue (l'e-mail part sans pièce jointe). */
    protected function pdfSigne(string $vue, array $donnees, string $nom, Model $objet, string $type, string $etat): ?int
    {
        try {
            return \App\Support\Pdf::generer($vue, $donnees, str($nom)->slug() . '.pdf', objet: $objet, type: $type, etat: $etat)[1]?->id;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function majQuittance(Loyer $loyer): void
    {
        if ($loyer->estSolde() && !$loyer->quittance_numero) {
            $loyer->update(['quittance_numero' => $this->numerotation->suivant('quittance')]);
        }
    }

    protected function majAppel(AppelFonds $appel): void
    {
        if (in_array($appel->statut, [StatutAppelFonds::Emis, StatutAppelFonds::Solde], true)) {
            $appel->update(['statut' => $appel->lignes()->impayes()->exists() ? StatutAppelFonds::Emis : StatutAppelFonds::Solde]);
        }
    }

    protected function dateDette(Model $dette)
    {
        return match (true) {
            $dette instanceof AppelLigne => $dette->appel?->date_echeance,
            $dette instanceof Sejour => $dette->date_arrivee,
            $dette instanceof VenteFrais => $dette->created_at,
            default => $dette->date_echeance,
        };
    }
}
