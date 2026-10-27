<?php

namespace App\Services\Rh;

use App\Models\Rh\Absence;
use App\Models\Rh\Avance;
use App\Models\Rh\Contrat;
use App\Models\Rh\Employe;
use App\Models\Rh\Presence;
use App\Services\Compta\ComptaService;
use App\Services\Numerotation;
use App\Services\Parametres;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gestion du personnel : embauche, contrats, carrière, congés (demande / validation / solde), présences, avances.
 */
class RhService
{
    public function __construct(protected Parametres $parametres, protected Numerotation $numerotation, protected ComptaService $compta)
    {
    }

    public function embaucher(array $employe, array $contrat): Employe
    {
        return DB::transaction(function () use ($employe, $contrat) {
            $employe['matricule'] = ($employe['matricule'] ?? null) ?: $this->prochainMatricule();
            $employe['nombre_parts'] = ($employe['nombre_parts'] ?? null) ?: Employe::partsFiscales($employe['situation_familiale'] ?? null, (int) ($employe['nombre_enfants'] ?? 0));
            $e = Employe::create($employe);

            $e->contrats()->create($contrat + ['agence_id' => $e->agence_id, 'date_debut' => $contrat['date_debut'] ?? $e->date_embauche]);
            $e->historique()->create(['type' => 'embauche', 'date_effet' => $e->date_embauche, 'libelle' => 'Embauche — ' . ($contrat['type_contrat'] ?? 'CDI'),
                'details' => ['salaire_base' => $contrat['salaire_base'] ?? 0], 'user_id' => auth()->id()]);

            return $e;
        });
    }

    /**
     * Nouveau contrat (avenant, renouvellement, augmentation) : l'ancien est clos, la carrière est tracée.
     */
    public function nouveauContrat(Employe $e, array $donnees, string $type = 'augmentation', ?string $libelle = null): Contrat
    {
        return DB::transaction(function () use ($e, $donnees, $type, $libelle) {
            $ancien = $e->contratActif;
            $ancien?->update(['statut' => 'clos', 'date_fin' => Carbon::parse($donnees['date_debut'])->subDay()]);
            $contrat = $e->contrats()->create($donnees + ['agence_id' => $e->agence_id, 'statut' => 'actif']);

            $e->historique()->create([
                'type' => $type, 'date_effet' => $donnees['date_debut'], 'libelle' => $libelle ?? 'Nouveau contrat ' . $contrat->type_contrat,
                'details' => ['avant' => $ancien?->only(['type_contrat', 'salaire_base', 'categorie']), 'apres' => $contrat->only(['type_contrat', 'salaire_base', 'categorie'])],
                'user_id' => auth()->id(),
            ]);

            return $contrat;
        });
    }

    public function sortie(Employe $e, Carbon $date, string $motif): void
    {
        DB::transaction(function () use ($e, $date, $motif) {
            $e->update(['statut' => 'sorti', 'date_sortie' => $date, 'motif_sortie' => $motif]);
            $e->contratActif?->update(['statut' => 'clos', 'date_fin' => $date]);
            $e->historique()->create(['type' => 'sortie', 'date_effet' => $date, 'libelle' => 'Sortie : ' . $motif, 'user_id' => auth()->id()]);
        });
    }

    /**
     * Jours ouvrables entre deux dates (dimanches exclus).
     */
    public static function joursOuvrables(Carbon $debut, Carbon $fin): float
    {
        $jours = 0;
        for ($d = $debut->copy(); $d->lte($fin); $d->addDay()) {
            if (!$d->isSunday()) {
                $jours++;
            }
        }

        return (float) $jours;
    }

    public function demanderAbsence(Employe $e, string $type, Carbon $debut, Carbon $fin, ?string $motif = null): Absence
    {
        if ($fin->lt($debut)) {
            throw new DomainException('La date de fin doit suivre la date de début.');
        }
        $chevauche = Absence::where('employe_id', $e->id)->whereIn('statut', ['demandee', 'validee'])
            ->whereDate('date_debut', '<=', $fin)->whereDate('date_fin', '>=', $debut)->exists();
        if ($chevauche) {
            throw new DomainException('Une absence est déjà demandée ou validée sur cette période.');
        }

        $jours = self::joursOuvrables($debut, $fin);
        if (in_array($type, Absence::DECOMPTEES, true) && $jours > (float) $e->solde_conges) {
            throw new DomainException('Solde de congés insuffisant : ' . (float) $e->solde_conges . ' jour(s) disponible(s) pour ' . $jours . ' demandé(s).');
        }

        return Absence::create(['employe_id' => $e->id, 'type' => $type, 'date_debut' => $debut, 'date_fin' => $fin, 'nombre_jours' => $jours,
            'motif' => $motif, 'statut' => 'demandee', 'created_by' => auth()->id()]);
    }

    public function decider(Absence $a, bool $valider, ?string $commentaire = null): void
    {
        if ($a->statut !== 'demandee') {
            throw new DomainException('Cette demande a déjà été traitée.');
        }

        DB::transaction(function () use ($a, $valider, $commentaire) {
            $employe = Employe::whereKey($a->employe_id)->lockForUpdate()->first();
            if ($valider && in_array($a->type, Absence::DECOMPTEES, true)) {
                if ((float) $a->nombre_jours > (float) $employe->solde_conges) {
                    throw new DomainException('Solde de congés insuffisant pour valider cette demande.');
                }
                $employe->decrement('solde_conges', (float) $a->nombre_jours);
            }

            $a->update(['statut' => $valider ? 'validee' : 'refusee', 'decide_par' => auth()->id(), 'decide_le' => now(), 'commentaire_decision' => $commentaire]);

            if ($valider) {
                for ($d = $a->date_debut->copy(); $d->lte($a->date_fin); $d->addDay()) {
                    if (!$d->isSunday()) {
                        Presence::updateOrCreate(['employe_id' => $a->employe_id, 'date' => $d->toDateString()],
                            ['agence_id' => $a->agence_id, 'statut' => in_array($a->type, Absence::NON_REMUNEREES, true) ? 'absent' : 'conge', 'commentaire' => 'Absence validée']);
                    }
                }
            }
        });
    }

    public function annulerAbsence(Absence $a): void
    {
        if (!in_array($a->statut, ['demandee', 'validee'], true)) {
            throw new DomainException('Cette absence ne peut plus être annulée.');
        }

        DB::transaction(function () use ($a) {
            if ($a->statut === 'validee' && in_array($a->type, Absence::DECOMPTEES, true)) {
                Employe::whereKey($a->employe_id)->increment('solde_conges', (float) $a->nombre_jours);
            }
            Presence::where('employe_id', $a->employe_id)->whereBetween('date', [$a->date_debut, $a->date_fin])->where('commentaire', 'Absence validée')->delete();
            $a->update(['statut' => 'annulee']);
        });
    }

    /**
     * Acquisition mensuelle des congés payés (tâche du 1er du mois) : idempotente par mois via l'historique.
     */
    public function acquerirConges(?Carbon $mois = null): int
    {
        $mois ??= today()->subMonthNoOverflow()->startOfMonth();
        $libelle = 'Acquisition congés ' . $mois->format('m/Y');
        $jours = (float) $this->parametres->get('rh.jours_conges_mois');
        $n = 0;

        foreach (Employe::where('statut', 'actif')->whereDate('date_embauche', '<=', $mois->copy()->endOfMonth())->get() as $e) {
            if ($e->historique()->where('libelle', $libelle)->exists()) {
                continue;
            }
            $e->increment('solde_conges', $jours);
            $e->historique()->create(['type' => 'conges', 'date_effet' => $mois->copy()->endOfMonth(), 'libelle' => $libelle, 'details' => ['jours' => $jours]]);
            $n++;
        }

        return $n;
    }

    public function accorderAvance(Employe $e, array $donnees): Avance
    {
        $montant = round((float) $donnees['montant'], 2);
        $echeances = max(1, (int) ($donnees['nombre_echeances'] ?? 1));

        return DB::transaction(function () use ($e, $donnees, $montant, $echeances) {
            $avance = Avance::create([
                'employe_id' => $e->id,
                'numero' => $this->numerotation->suivant('avance'),
                'type' => $donnees['type'] ?? 'avance',
                'date_octroi' => $donnees['date_octroi'] ?? today(),
                'montant' => $montant,
                'nombre_echeances' => $echeances,
                'montant_echeance' => round($montant / $echeances, 2),
                'mode_paiement' => $donnees['mode_paiement'] ?? 'ESPECES',
                'motif' => $donnees['motif'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $this->compta->tenter(fn () => $this->compta->avance($avance));

            return $avance;
        });
    }

    protected function prochainMatricule(): string
    {
        $prefixe = $this->parametres->get('rh.prefixe_matricule') ?: 'EMP';
        $n = Employe::withTrashed()->count();
        do {
            $matricule = sprintf('%s-%04d', $prefixe, ++$n);
        } while (Employe::withTrashed()->where('matricule', $matricule)->exists());

        return $matricule;
    }
}
