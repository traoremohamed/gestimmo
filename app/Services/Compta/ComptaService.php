<?php

namespace App\Services\Compta;

use App\Enums\NatureLoyer;
use App\Models\Compta\Compte;
use App\Models\Compta\Ecriture;
use App\Models\Compta\Exercice;
use App\Models\Compta\Journal;
use App\Models\Compta\Ligne;
use App\Models\Compta\Schema;
use App\Models\Depense;
use App\Models\Encaissement;
use App\Models\Loyer;
use App\Models\Reversement;
use App\Models\Rh\Avance;
use App\Models\Rh\PaiementSalaire;
use App\Models\Rh\PeriodePaie;
use App\Services\Parametres;
use App\Support\AgenceCourante;
use App\Support\Montant;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Comptabilité SYSCOHADA alimentée par les opérations (modèle repris de GEST COMM).
 *
 * Chaque encaissement, dépense, reversement, paie, paiement de salaire ou avance produit une écriture
 * équilibrée selon les règles d'imputation de l'agence (cpt_schemas). Une annulation est contre-passée.
 * Les fonds de tiers restent en comptes de tiers : loyers des biens en gestion (4671), fonds des copropriétés (4672).
 */
class ComptaService
{
    public function __construct(protected AgenceCourante $agenceCourante, protected Parametres $parametres)
    {
    }

    public function actif(): bool
    {
        return $this->agenceCourante->moduleActif('comptabilite') && (bool) $this->parametres->get('compta.ecritures_auto');
    }

    /**
     * Exécute une passation sans jamais bloquer l'opération métier ; l'écriture manquante
     * pourra être générée plus tard (Comptabilité › Générer depuis les opérations).
     */
    public function tenter(callable $passation): ?Ecriture
    {
        if (!$this->actif()) {
            return null;
        }

        try {
            return DB::transaction(fn () => $passation());
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    // ------------------------------------------------------------------ référentiel

    public function compte(string $numero): Compte
    {
        return Compte::where('numero', $numero)->first()
            ?? Compte::create(['numero' => $numero, 'libelle' => 'Compte ' . $numero, 'classe' => (int) substr($numero, 0, 1), 'actif' => true]);
    }

    /**
     * @return array{0: Compte, 1: ?string} compte et journal de la règle
     */
    public function regle(string $operation, string $cle, ?string $defaut = null): array
    {
        $schema = Schema::where('operation', $operation)->where('cle', $cle)->first();
        if (!$schema && $defaut === null) {
            throw new DomainException("Règle d'imputation manquante : {$operation} / {$cle} (Comptabilité › Plan comptable & imputations).");
        }

        return [$this->compte($schema?->compte ?? $defaut), $schema?->journal];
    }

    public function journal(string $code): Journal
    {
        return Journal::firstOrCreate(['code' => $code], ['libelle' => 'Journal ' . $code, 'type' => 'OD', 'actif' => true]);
    }

    public function exercice(Carbon|string $date): Exercice
    {
        $date = Carbon::parse($date);
        $exercice = Exercice::whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first();

        if (!$exercice) {
            $mois = max(1, min(12, (int) $this->parametres->get('compta.debut_exercice_mois')));
            $debut = Carbon::create($date->month >= $mois ? $date->year : $date->year - 1, $mois, 1);
            $exercice = Exercice::create([
                'code' => $mois === 1 ? (string) $debut->year : $debut->year . '-' . ($debut->year + 1),
                'date_debut' => $debut, 'date_fin' => $debut->copy()->addYear()->subDay(), 'statut' => 'ouvert',
            ]);
        }
        if (!$exercice->estOuvert()) {
            throw new DomainException("L'exercice {$exercice->code} est clôturé : aucune écriture au " . $date->format('d/m/Y') . '.');
        }

        return $exercice;
    }

    // ------------------------------------------------------------------ passation

    /**
     * @param  array<int, array{compte: Compte|string, debit?: mixed, credit?: mixed, libelle?: string, tiers_id?: ?int}>  $lignes
     */
    public function passer(string $journal, Carbon|string $date, string $libelle, array $lignes, ?Model $source = null, ?string $piece = null,
        string $nature = 'normale', ?Ecriture $origine = null, ?string $operation = null): Ecriture
    {
        // regroupement par compte / tiers / libellé, montants nuls écartés
        $groupes = [];
        foreach ($lignes as $l) {
            $compte = $l['compte'] instanceof Compte ? $l['compte'] : $this->compte($l['compte']);
            $cle = $compte->id . '|' . ($l['tiers_id'] ?? '') . '|' . ($l['libelle'] ?? $libelle);
            $groupes[$cle] ??= ['compte' => $compte, 'debit' => '0.00', 'credit' => '0.00', 'libelle' => $l['libelle'] ?? $libelle, 'tiers_id' => $l['tiers_id'] ?? null];
            $groupes[$cle]['debit'] = Montant::add($groupes[$cle]['debit'], $l['debit'] ?? 0);
            $groupes[$cle]['credit'] = Montant::add($groupes[$cle]['credit'], $l['credit'] ?? 0);
        }

        $retenues = [];
        foreach ($groupes as $g) {
            $net = Montant::sub($g['debit'], $g['credit']);
            if (Montant::compare($net, 0) === 0) {
                continue;
            }
            $retenues[] = $g + ['debit_net' => Montant::estPositif($net) ? $net : '0.00', 'credit_net' => Montant::estPositif($net) ? '0.00' : Montant::sub(0, $net)];
        }

        $debit = Montant::add(...array_column($retenues, 'debit_net'));
        $credit = Montant::add(...array_column($retenues, 'credit_net'));
        if (count($retenues) < 2 || !Montant::estPositif($debit)) {
            throw new DomainException('Une écriture comporte au moins deux lignes et un montant positif.');
        }
        if (Montant::compare($debit, $credit) !== 0) {
            throw new DomainException("Écriture déséquilibrée : débit {$debit} ≠ crédit {$credit}.");
        }

        return DB::transaction(function () use ($journal, $date, $libelle, $retenues, $source, $piece, $nature, $origine, $operation, $debit) {
            $journal = $this->journal($journal);
            $exercice = $this->exercice($date);
            Exercice::whereKey($exercice->id)->lockForUpdate()->first(); // numérotation sans trou ni doublon
            $prefixe = $journal->code . '-' . $exercice->code . '-';
            $dernier = Ecriture::where('numero', 'like', $prefixe . '%')->max('numero');

            $ecriture = Ecriture::create([
                'exercice_id' => $exercice->id,
                'journal_id' => $journal->id,
                'numero' => $prefixe . str_pad((string) ((int) substr((string) $dernier, strlen($prefixe)) + 1), 6, '0', STR_PAD_LEFT),
                'date_ecriture' => Carbon::parse($date)->toDateString(),
                'libelle' => mb_substr($libelle, 0, 255),
                'piece' => $piece,
                'operation' => $operation,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'nature' => $nature,
                'ecriture_origine_id' => $origine?->id,
                'total' => $debit,
                'created_by' => auth()->id(),
            ]);

            foreach ($retenues as $l) {
                $ecriture->lignes()->create([
                    'compte_id' => $l['compte']->id, 'libelle' => mb_substr($l['libelle'], 0, 255),
                    'debit' => $l['debit_net'], 'credit' => $l['credit_net'], 'tiers_id' => $l['tiers_id'],
                ]);
            }

            return $ecriture;
        });
    }

    /**
     * Contre-passation : écriture inverse, datée du jour de l'annulation.
     */
    public function contrepasser(Ecriture $ecriture, Carbon|string|null $date = null, ?string $motif = null): Ecriture
    {
        $ecriture->loadMissing('lignes.compte', 'journal');

        return $this->passer($ecriture->journal->code, $date ?? today(), 'Annulation ' . $ecriture->numero . ($motif ? ' — ' . $motif : ''),
            $ecriture->lignes->map(fn ($l) => ['compte' => $l->compte, 'debit' => $l->credit, 'credit' => $l->debit, 'libelle' => $l->libelle, 'tiers_id' => $l->tiers_id])->all(),
            $ecriture->source, $ecriture->piece, 'contrepassation', $ecriture, $ecriture->operation);
    }

    public function dejaPassee(Model $source): ?Ecriture
    {
        return Ecriture::where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->where('nature', 'normale')->first();
    }

    public function annulerPiece(Model $source, ?string $motif = null): ?Ecriture
    {
        $ecriture = $this->dejaPassee($source);
        if (!$ecriture || Ecriture::where('ecriture_origine_id', $ecriture->id)->exists()) {
            return null;
        }

        return $this->contrepasser($ecriture, today(), $motif);
    }

    // ------------------------------------------------------------------ opérations

    /** @return array{0: Compte, 1: string} */
    protected function tresorerie(?string $mode): array
    {
        [$compte, $journal] = Schema::where('operation', 'TRESORERIE')->where('cle', $mode)->exists()
            ? $this->regle('TRESORERIE', (string) $mode)
            : $this->regle('TRESORERIE', 'AUTRE', '5711');

        return [$compte, $journal ?? 'CA'];
    }

    /**
     * Encaissement : trésorerie au débit, contrepartie de chaque dette réglée au crédit.
     */
    public function encaissement(Encaissement $e): ?Ecriture
    {
        if ($e->estAnnule() || $this->dejaPassee($e)) {
            return null;
        }
        $e->loadMissing('affectations.affectable', 'tiers');
        [$tresorerie, $journal] = $this->tresorerie($e->mode_paiement);

        $lignes = [['compte' => $tresorerie, 'debit' => $e->montant, 'libelle' => 'Reçu ' . $e->numero . ' — ' . $e->tiers?->nom_complet]];
        foreach ($e->affectations as $a) {
            [$compte] = $this->regle('ENCAISSEMENT', $this->posteEncaissement($a->affectable), '471');
            $lignes[] = ['compte' => $compte, 'credit' => $a->montant, 'libelle' => mb_substr((string) $a->affectable?->libelleAffectation(), 0, 255) ?: 'Encaissement', 'tiers_id' => $e->tiers_id];
        }

        return $this->passer($journal, $e->date_encaissement, 'Encaissement ' . $e->numero . ' — ' . $e->tiers?->nom_complet, $lignes, $e, $e->numero, operation: 'ENCAISSEMENT');
    }

    protected function posteEncaissement(?Model $dette): string
    {
        if (!$dette instanceof Loyer) {
            return $dette?->getMorphClass() ?? 'autre';
        }

        $mandat = (bool) $dette->bail?->bien?->proprietaire_id;

        return match ($dette->nature) {
            NatureLoyer::Loyer, NatureLoyer::Regularisation => $mandat ? 'loyer_mandat' : 'loyer',
            NatureLoyer::Avance => $mandat ? 'avance_mandat' : 'avance',
            NatureLoyer::Penalite => $mandat ? 'penalite_mandat' : 'penalite',
            NatureLoyer::DepotGarantie => 'depot_garantie',
            NatureLoyer::FraisAgence => 'frais_agence',
            NatureLoyer::CautionSodeci, NatureLoyer::CautionCie => 'caution_compteur',
            NatureLoyer::Retenue => $mandat ? 'retenue_mandat' : 'retenue',
        };
    }

    /**
     * Dépense payée : charge (ou compte du mandant / de la copropriété) au débit, trésorerie au crédit.
     */
    public function depense(Depense $d): ?Ecriture
    {
        if ($d->statut !== 'payee' || $this->dejaPassee($d)) {
            return null;
        }

        $poste = match (true) {
            $d->copropriete_id !== null => 'COPROPRIETE',
            (bool) $d->refacturable_proprietaire => 'REFACTURABLE',
            default => $d->categorie,
        };
        [$charge] = Schema::where('operation', 'DEPENSE')->where('cle', $poste)->exists() ? $this->regle('DEPENSE', $poste) : $this->regle('DEPENSE', 'AUTRE', '6588');
        [$tresorerie, $journal] = $this->tresorerie($d->mode_paiement);
        $tiers = $d->beneficiaire_id ?? $d->fournisseur_id;

        return $this->passer($journal, $d->date_depense, 'Dépense ' . $d->numero . ' — ' . $d->libelle, [
            ['compte' => $charge, 'debit' => $d->montant, 'libelle' => $d->libelle, 'tiers_id' => $tiers],
            ['compte' => $tresorerie, 'credit' => $d->montant, 'libelle' => $d->libelle],
        ], $d, $d->numero, operation: 'DEPENSE');
    }

    /**
     * Reversement payé : le compte du mandant est soldé par la commission (produit) et le versement (trésorerie).
     */
    public function reversement(Reversement $r): ?Ecriture
    {
        if ($r->statut->value !== 'paye' || $this->dejaPassee($r)) {
            return null;
        }
        [$mandant] = $this->regle('REVERSEMENT', 'mandant', '4671');
        [$commission] = $this->regle('REVERSEMENT', 'commission', '7061');
        [$tresorerie, $journal] = $this->tresorerie($r->mode_paiement);
        $libelle = 'Reversement ' . $r->numero . ' — ' . $r->proprietaire?->nom_complet;

        return $this->passer($journal, $r->paye_le ?? today(), $libelle, [
            ['compte' => $mandant, 'debit' => Montant::add($r->montant_commission, $r->montant_net), 'libelle' => $libelle, 'tiers_id' => $r->proprietaire_id],
            ['compte' => $commission, 'credit' => $r->montant_commission, 'libelle' => 'Commission de gestion ' . $r->numero],
            ['compte' => $tresorerie, 'credit' => $r->montant_net, 'libelle' => $libelle],
        ], $r, $r->numero, operation: 'REVERSEMENT');
    }

    /**
     * Paie validée : charges de personnel et cotisations patronales au débit ; salaires nets, organismes sociaux,
     * impôts et avances au crédit (une écriture par période).
     */
    public function paie(PeriodePaie $periode): ?Ecriture
    {
        if ($this->dejaPassee($periode)) {
            return null;
        }
        $periode->loadMissing('bulletins.employe');
        $rubriques = \App\Models\Rh\Rubrique::pluck('compte_charge', 'code')->all();
        $tiersRubriques = \App\Models\Rh\Rubrique::pluck('compte_tiers', 'code')->all();
        [$net] = $this->regle('PAIE', 'net', '422');
        $lignes = [];

        foreach ($periode->bulletins->where('statut', '!=', 'annule') as $b) {
            foreach ($b->lignes as $l) {
                $montant = $l['montant'];
                $charge = $rubriques[$l['code']] ?? null;
                $tiers = $tiersRubriques[$l['code']] ?? null;
                match ($l['type']) {
                    'GAIN' => $lignes[] = ['compte' => $charge ?: '6611', 'debit' => $montant, 'libelle' => $l['libelle']],
                    'COTISATION_PATRONALE' => array_push($lignes,
                        ['compte' => $charge ?: '6641', 'debit' => $montant, 'libelle' => $l['libelle']],
                        ['compte' => $tiers ?: '4311', 'credit' => $montant, 'libelle' => $l['libelle']]),
                    default => $lignes[] = ['compte' => $tiers ?: '422', 'credit' => $montant, 'libelle' => $l['libelle']],
                };
            }
            $lignes[] = ['compte' => $net, 'credit' => $b->net_a_payer, 'libelle' => 'Net à payer ' . $b->employe->nom_complet];
            if ((float) ($b->totaux['ARRONDI'] ?? 0) !== 0.0) {
                $arrondi = (float) $b->totaux['ARRONDI'];
                $lignes[] = ['compte' => $arrondi > 0 ? '6588' : '7588', $arrondi > 0 ? 'debit' : 'credit' => abs($arrondi), 'libelle' => 'Arrondi de paie'];
            }
        }

        return $this->passer('PA', $periode->date_fin, 'Paie ' . $periode->libelle, $lignes, $periode, $periode->code, operation: 'PAIE');
    }

    public function paiementSalaire(PaiementSalaire $p): ?Ecriture
    {
        if ($p->statut !== 'valide' || $this->dejaPassee($p)) {
            return null;
        }
        [$net] = $this->regle('PAIE', 'net', '422');
        [$tresorerie, $journal] = $this->tresorerie($p->mode_paiement);
        $libelle = 'Salaire ' . $p->employe?->nom_complet . ' — ' . $p->numero;

        return $this->passer($journal, $p->date_paiement, $libelle, [
            ['compte' => $net, 'debit' => $p->montant, 'libelle' => $libelle],
            ['compte' => $tresorerie, 'credit' => $p->montant, 'libelle' => $libelle],
        ], $p, $p->numero, operation: 'PAIE');
    }

    public function avance(Avance $a): ?Ecriture
    {
        if ($a->statut === 'annulee' || $this->dejaPassee($a)) {
            return null;
        }
        [$compte] = $this->regle('PAIE', 'avance', '421');
        [$tresorerie, $journal] = $this->tresorerie($a->mode_paiement);
        $libelle = ucfirst($a->type) . ' ' . $a->numero . ' — ' . $a->employe?->nom_complet;

        return $this->passer($journal, $a->date_octroi, $libelle, [
            ['compte' => $compte, 'debit' => $a->montant, 'libelle' => $libelle],
            ['compte' => $tresorerie, 'credit' => $a->montant, 'libelle' => $libelle],
        ], $a, $a->numero, operation: 'AVANCE');
    }

    /**
     * Rattrapage : passe les écritures manquantes des opérations de la période (données reprises, module activé après coup…).
     *
     * @return array<string, int>
     */
    public function generer(Carbon|string $du, Carbon|string $au): array
    {
        $resultat = ['encaissements' => 0, 'annulations' => 0, 'depenses' => 0, 'reversements' => 0, 'paies' => 0, 'salaires' => 0, 'avances' => 0, 'erreurs' => 0];
        $essayer = function (callable $f, string $cle) use (&$resultat) {
            try {
                DB::transaction(fn () => $f()) && $resultat[$cle]++;
            } catch (\Throwable $e) {
                report($e);
                $resultat['erreurs']++;
            }
        };

        foreach (Encaissement::whereBetween('date_encaissement', [$du, $au])->get() as $e) {
            if (!$this->dejaPassee($e)) {
                $essayer(function () use ($e) {
                    $ecriture = $this->passerSansControleStatut($e);
                    if ($ecriture && $e->estAnnule()) {
                        $this->contrepasser($ecriture, $e->annule_le ?? today(), $e->motif_annulation);
                    }

                    return $ecriture;
                }, 'encaissements');
            }
        }
        foreach (Depense::where('statut', 'payee')->whereBetween('date_depense', [$du, $au])->get() as $d) {
            $essayer(fn () => $this->depense($d), 'depenses');
        }
        foreach (Reversement::where('statut', 'paye')->whereBetween('paye_le', [Carbon::parse($du)->startOfDay(), Carbon::parse($au)->endOfDay()])->get() as $r) {
            $essayer(fn () => $this->reversement($r), 'reversements');
        }
        foreach (PeriodePaie::whereIn('statut', ['validee', 'payee', 'cloturee'])->whereBetween('date_fin', [$du, $au])->get() as $p) {
            $essayer(fn () => $this->paie($p), 'paies');
        }
        foreach (PaiementSalaire::where('statut', 'valide')->whereBetween('date_paiement', [$du, $au])->get() as $p) {
            $essayer(fn () => $this->paiementSalaire($p), 'salaires');
        }
        foreach (Avance::where('statut', '!=', 'annulee')->whereBetween('date_octroi', [$du, $au])->get() as $a) {
            $essayer(fn () => $this->avance($a), 'avances');
        }

        return $resultat;
    }

    /** encaissement annulé repris : l'écriture d'origine puis sa contre-passation */
    protected function passerSansControleStatut(Encaissement $e): ?Ecriture
    {
        $statut = $e->statut;
        $e->statut = 'valide';
        try {
            return $this->encaissement($e);
        } finally {
            $e->statut = $statut;
        }
    }

    // ------------------------------------------------------------------ états

    /**
     * Balance : mouvements de la période et soldes par compte.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function balance(Carbon|string $du, Carbon|string $au, ?int $classe = null)
    {
        return Ligne::query()
            ->join('cpt_ecritures as e', 'e.id', '=', 'cpt_lignes.ecriture_id')
            ->join('cpt_comptes as c', 'c.id', '=', 'cpt_lignes.compte_id')
            ->where('e.agence_id', $this->agenceCourante->id())
            ->whereBetween('e.date_ecriture', [Carbon::parse($du)->toDateString(), Carbon::parse($au)->toDateString()])
            ->when($classe, fn ($q) => $q->where('c.classe', $classe))
            ->groupBy('c.id', 'c.numero', 'c.libelle', 'c.classe', 'c.type')
            ->orderBy('c.numero')
            ->selectRaw('c.id, c.numero, c.libelle, c.classe, c.type, sum(cpt_lignes.debit) as debit, sum(cpt_lignes.credit) as credit, sum(cpt_lignes.debit) - sum(cpt_lignes.credit) as solde')
            ->get();
    }

    public function grandLivre(Compte $compte, Carbon|string $du, Carbon|string $au): array
    {
        $du = Carbon::parse($du)->toDateString();
        $base = Ligne::where('compte_id', $compte->id)->join('cpt_ecritures as e', 'e.id', '=', 'cpt_lignes.ecriture_id');
        $report = (clone $base)->where('e.date_ecriture', '<', $du)->selectRaw('coalesce(sum(debit) - sum(credit), 0) as s')->value('s');
        $lignes = (clone $base)->whereBetween('e.date_ecriture', [$du, Carbon::parse($au)->toDateString()])
            ->orderBy('e.date_ecriture')->orderBy('e.id')
            ->get(['cpt_lignes.*', 'e.date_ecriture', 'e.numero', 'e.piece', 'e.id as ecriture']);

        return ['report' => (float) $report, 'lignes' => $lignes];
    }

    /**
     * Bilan et compte de résultat simplifiés (par classe et type de compte).
     */
    public function etatsFinanciers(Carbon|string $du, Carbon|string $au): array
    {
        $balance = $this->balance(Carbon::parse($du)->subYears(50), $au); // bilan : soldes cumulés
        $periode = $this->balance($du, $au);

        $produits = $periode->where('classe', 7)->sum(fn ($l) => -(float) $l->solde);
        $charges = $periode->where('classe', 6)->sum(fn ($l) => (float) $l->solde);

        $actif = $balance->filter(fn ($l) => in_array($l->classe, [2, 3, 5], true) || ($l->classe === 4 && (float) $l->solde > 0));
        $passif = $balance->filter(fn ($l) => $l->classe === 1 || ($l->classe === 4 && (float) $l->solde < 0));
        $resultatCumule = $balance->whereIn('classe', [6, 7])->sum(fn ($l) => -(float) $l->solde);

        return [
            'produits' => $periode->where('classe', 7)->values(),
            'charges' => $periode->where('classe', 6)->values(),
            'total_produits' => round($produits, 2),
            'total_charges' => round($charges, 2),
            'resultat' => round($produits - $charges, 2),
            'actif' => $actif->values(),
            'passif' => $passif->values(),
            'total_actif' => round($actif->sum(fn ($l) => (float) $l->solde), 2),
            'total_passif' => round(-$passif->sum(fn ($l) => (float) $l->solde) + $resultatCumule, 2),
            'resultat_cumule' => round($resultatCumule, 2),
        ];
    }

    /**
     * Clôture : solde les comptes de gestion (6 et 7) dans le résultat (131 / 139) et ferme l'exercice.
     */
    public function cloturer(Exercice $exercice): array
    {
        if (!$exercice->estOuvert()) {
            throw new DomainException('Cet exercice est déjà clôturé.');
        }

        return DB::transaction(function () use ($exercice) {
            $balance = $this->balance($exercice->date_debut, $exercice->date_fin)->whereIn('classe', [6, 7]);
            $resultat = round($balance->sum(fn ($l) => -(float) $l->solde), 2);

            if ($balance->isNotEmpty() && $resultat != 0.0) {
                $lignes = $balance->map(fn ($l) => ['compte' => $l->numero, (float) $l->solde > 0 ? 'credit' : 'debit' => abs((float) $l->solde), 'libelle' => 'Clôture ' . $l->libelle])->all();
                $lignes[] = ['compte' => $resultat >= 0 ? '131' : '139', $resultat >= 0 ? 'credit' : 'debit' => abs($resultat), 'libelle' => 'Résultat de l\'exercice ' . $exercice->code];
                $this->passer('OD', $exercice->date_fin, 'Clôture de l\'exercice ' . $exercice->code, $lignes, $exercice, $exercice->code, 'cloture');
            }

            $exercice->update(['statut' => 'cloture', 'resultat' => $resultat, 'cloture_le' => now(), 'cloture_par' => auth()->id()]);

            return ['resultat' => $resultat];
        });
    }
}
