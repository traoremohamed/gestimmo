<?php

namespace App\Services\Rh;

use App\Models\Rh\Absence;
use App\Models\Rh\Avance;
use App\Models\Rh\Bulletin;
use App\Models\Rh\Contrat;
use App\Models\Rh\PaiementSalaire;
use App\Models\Rh\PeriodePaie;
use App\Models\Rh\Presence;
use App\Models\Rh\Rubrique;
use App\Models\Rh\VariablePaie;
use App\Models\Vente;
use App\Services\Compta\ComptaService;
use App\Services\Numerotation;
use App\Services\Parametres;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paie mensuelle (moteur de rubriques repris de GEST COMM) :
 *  période -> calcul des bulletins -> validation (écriture de paie, remboursement des avances) -> paiements.
 * Modes de rémunération : FIXE (prorata des absences non payées et des entrées / sorties), JOURNALIER (présences),
 * COMMISSION (fixe + % du chiffre d'affaires des ventes validées par le commercial).
 */
class PaieService
{
    public function __construct(protected Parametres $parametres, protected Numerotation $numerotation, protected ComptaService $compta)
    {
    }

    public function periode(Carbon|string $mois): PeriodePaie
    {
        $debut = Carbon::parse($mois)->startOfMonth();

        return PeriodePaie::firstOrCreate(['code' => $debut->format('Y-m')], [
            'libelle' => ucfirst($debut->locale('fr')->translatedFormat('F Y')),
            'date_debut' => $debut,
            'date_fin' => $debut->copy()->endOfMonth(),
            'date_paiement' => $debut->copy()->day(min((int) $this->parametres->get('paie.jour_paiement'), $debut->daysInMonth)),
            'statut' => 'ouverte',
        ]);
    }

    public static function bareme(array $tranches, float $assiette): float
    {
        $impot = 0.0;
        foreach ($tranches as [$de, $a, $taux]) {
            if ($assiette <= $de) {
                break;
            }
            $impot += (min($assiette, $a ?? PHP_FLOAT_MAX) - $de) * $taux / 100;
        }

        return $impot;
    }

    public static function reductionParts(?array $table, float $parts): float
    {
        if (!$table) {
            return 0.0;
        }
        $cle = rtrim(rtrim(number_format($parts, 1, '.', ''), '0'), '.');

        return (float) ($table[$cle] ?? end($table));
    }

    /**
     * @return array{jours: float, absences: float, heures_sup: float}
     */
    public function pointage(Contrat $c, PeriodePaie $p): array
    {
        $presences = Presence::where('employe_id', $c->employe_id)->whereBetween('date', [$p->date_debut, $p->date_fin])->get();

        return [
            'jours' => (float) $presences->sum(fn ($x) => match ($x->statut) { 'present', 'retard' => 1, 'demi_journee' => 0.5, default => 0 }),
            'absences' => (float) $presences->where('statut', 'absent')->count(),
            'heures_sup' => (float) $presences->sum('heures_supplementaires'),
        ];
    }

    public function caVendeur(Contrat $c, PeriodePaie $p): float
    {
        $userId = $c->employe?->user_id;

        return $userId ? (float) Vente::where('commercial_id', $userId)->whereNotNull('valide_le')
            ->whereBetween('date_contrat', [$p->date_debut, $p->date_fin])->sum('montant_net') : 0.0;
    }

    /**
     * Calcul complet d'un bulletin (sans enregistrement).
     */
    public function simuler(Contrat $c, PeriodePaie $p): array
    {
        $c->loadMissing('employe');
        $debut = $p->date_debut;
        $fin = $p->date_fin;
        $pt = $this->pointage($c, $p);
        $variables = VariablePaie::where('employe_id', $c->employe_id)->where('periode_id', $p->id)->get()->groupBy('rubrique_code');

        // prorata : entrée / sortie en cours de mois et absences non rémunérées
        $joursRef = (float) ($c->jours_par_mois ?: 26);
        $hors = 0;
        if ($c->date_debut->gt($debut)) {
            $hors += $debut->diffInDays($c->date_debut) * $joursRef / $debut->daysInMonth;
        }
        $sortie = $c->employe->date_sortie && $c->employe->date_sortie->lt($fin) ? $c->employe->date_sortie : ($c->date_fin && $c->date_fin->lt($fin) ? $c->date_fin : null);
        if ($sortie) {
            $hors += $sortie->diffInDays($fin) * $joursRef / $debut->daysInMonth;
        }
        $nonPayees = $pt['absences'] + (float) Absence::where('employe_id', $c->employe_id)->where('statut', 'validee')
            ->whereIn('type', Absence::NON_REMUNEREES)->where('date_debut', '<=', $fin)->where('date_fin', '>=', $debut)->sum('nombre_jours');
        $prorata = max(0, ($joursRef - min($joursRef, $hors) - min($joursRef, $nonPayees)) / $joursRef);

        $base = $c->mode_remuneration === 'JOURNALIER'
            ? round($pt['jours'] * (float) $c->taux_journalier, 2)
            : round(((float) $c->salaire_base + (float) $c->sursalaire) * $prorata, 2);

        $t = ['BASE' => $base, 'BRUT' => 0, 'BRUT_IMPOSABLE' => 0, 'BRUT_COTISABLE' => 0, 'COTISATIONS_SALARIALES' => 0, 'COTISATIONS_PATRONALES' => 0,
            'IMPOTS' => 0, 'RETENUES' => 0, 'AVANCES' => 0, 'CA_VENDEUR' => 0];
        $parCode = [];
        $lignes = [];

        foreach (Rubrique::where('actif', true)->orderBy('ordre')->orderBy('id')->get() as $r) {
            if (in_array($r->type, ['COTISATION_SALARIALE', 'COTISATION_PATRONALE'], true) && !$c->soumis_cotisations) {
                continue;
            }
            if ($r->type === 'IMPOT' && !$c->soumis_impot) {
                continue;
            }

            $assiette = null;
            $taux = $r->taux !== null ? (float) $r->taux : null;
            $nombre = null;
            $montant = 0.0;
            $plafond = $r->plafond ? (float) $r->plafond : PHP_FLOAT_MAX;

            switch ($r->mode_calcul) {
                case 'SALAIRE_BASE':
                    if ($c->mode_remuneration !== 'JOURNALIER') {
                        $montant = (float) $c->salaire_base * $prorata;
                        $assiette = (float) $c->salaire_base;
                        $nombre = $prorata < 1 ? round($prorata * 100, 1) . ' %' : null;
                    }
                    $taux = null;
                    break;
                case 'JOURS':
                    if ($c->mode_remuneration === 'JOURNALIER') {
                        $montant = $base;
                        $assiette = (float) $c->taux_journalier;
                        $nombre = $pt['jours'];
                    }
                    $taux = null;
                    break;
                case 'COMMISSION':
                    if ((float) $c->taux_commission > 0) {
                        $assiette = $this->caVendeur($c, $p);
                        $t['CA_VENDEUR'] = $assiette;
                        $taux = (float) $c->taux_commission;
                        $montant = $assiette * $taux / 100;
                    }
                    break;
                case 'FIXE':
                    $plein = (float) ($c->rubriques_fixes[$r->code] ?? ($r->code === 'SURSALAIRE' ? $c->sursalaire : $r->montant) ?? 0);
                    $montant = $r->proratiser ? $plein * $prorata : $plein;
                    $taux = null;
                    break;
                case 'SAISIE':
                    $montant = (float) ($variables[$r->code] ?? collect())->sum('montant');
                    if ($montant == 0.0 && $r->code === 'HS' && $pt['heures_sup'] > 0) {
                        $tauxHoraire = (float) $c->salaire_base / max(1, (float) $c->heures_hebdo * 4.33);
                        $montant = $pt['heures_sup'] * $tauxHoraire * (1 + (float) $this->parametres->get('paie.majoration_heures_sup') / 100);
                        $nombre = $pt['heures_sup'];
                    }
                    $taux = null;
                    break;
                case 'ANCIENNETE':
                    $ans = (int) $c->employe->date_embauche->diffInYears($fin);
                    if ($ans >= (int) ($r->anciennete_min_ans ?? 0)) {
                        $assiette = min($t[$r->base ?: 'BASE'] ?? $base, $plafond);
                        $taux = min($ans * (float) $r->taux, 25);
                        $montant = $assiette * $taux / 100;
                        $nombre = $ans . ' an(s)';
                    }
                    break;
                case 'POURCENTAGE':
                    $assiette = min((float) ($t[$r->base] ?? $parCode[$r->base] ?? 0), $plafond);
                    $montant = $assiette * (float) $taux / 100;
                    break;
                case 'BAREME':
                    $assiette = (float) ($t[$r->base] ?? 0);
                    $parts = max(1, (float) $c->employe->nombre_parts);
                    $brutImpot = self::bareme((array) $r->bareme, $assiette);
                    $montant = round(max(0, $brutImpot - min($brutImpot, self::reductionParts($r->reduction_parts, $parts))), 0);
                    $nombre = $parts . ' part(s)';
                    $taux = null;
                    break;
                case 'AVANCE':
                    $disponible = $t['BRUT'] - $t['COTISATIONS_SALARIALES'] - $t['IMPOTS'] - $t['RETENUES'];
                    $du = 0.0;
                    foreach (Avance::where('employe_id', $c->employe_id)->where('statut', 'en_cours')->whereDate('date_octroi', '<=', $fin)->get() as $a) {
                        $du += min((float) $a->montant_echeance, $a->reste());
                    }
                    $montant = max(0, min($du, $disponible));
                    $taux = null;
                    break;
            }

            $montant = round($montant, 2);
            $parCode[$r->code] = $montant;
            if ($montant == 0.0) {
                continue;
            }

            switch ($r->type) {
                case 'GAIN':
                    $franchise = min($montant, (float) ($r->franchise ?? 0));
                    $t['BRUT'] += $montant;
                    if ($r->imposable) {
                        $t['BRUT_IMPOSABLE'] += $montant - $franchise;
                    }
                    if ($r->cotisable) {
                        $t['BRUT_COTISABLE'] += $montant - $franchise;
                    }
                    break;
                case 'COTISATION_SALARIALE':
                    $t['COTISATIONS_SALARIALES'] += $montant;
                    break;
                case 'COTISATION_PATRONALE':
                    $t['COTISATIONS_PATRONALES'] += $montant;
                    break;
                case 'IMPOT':
                    $t['IMPOTS'] += $montant;
                    break;
                case 'RETENUE':
                    $t['RETENUES'] += $montant;
                    if ($r->mode_calcul === 'AVANCE') {
                        $t['AVANCES'] += $montant;
                    }
                    break;
            }

            $lignes[] = ['code' => $r->code, 'numero' => $r->numero ?: $r->code, 'libelle' => $r->libelle, 'type' => $r->type, 'groupe' => $r->groupe,
                'base' => $assiette !== null ? round($assiette, 2) : null, 'nombre' => $nombre, 'taux' => $taux, 'montant' => $montant];
        }

        $net = $t['BRUT'] - $t['COTISATIONS_SALARIALES'] - $t['IMPOTS'] - $t['RETENUES'];
        $pas = (int) $this->parametres->get('paie.arrondi');
        $arrondi = $pas > 1 ? round(round($net / $pas) * $pas - $net, 2) : 0.0;

        return ['lignes' => $lignes, 'pointage' => $pt, 'totaux' => $t + [
            'NET' => round($net + $arrondi, 2), 'ARRONDI' => $arrondi, 'COUT' => round($t['BRUT'] + $t['COTISATIONS_PATRONALES'], 2), 'PRORATA' => round($prorata, 4),
        ]];
    }

    public function calculer(Contrat $c, PeriodePaie $p): Bulletin
    {
        if (!$p->estModifiable()) {
            throw new DomainException('La période ' . $p->libelle . ' est validée : les bulletins ne peuvent plus être recalculés.');
        }

        $s = $this->simuler($c, $p);
        $t = $s['totaux'];
        $bulletin = Bulletin::firstOrNew(['employe_id' => $c->employe_id, 'periode_id' => $p->id]);
        $bulletin->fill([
            'numero' => $bulletin->numero ?? $this->numerotation->suivant('bulletin'),
            'contrat_id' => $c->id,
            'jours_travailles' => $c->mode_remuneration === 'JOURNALIER' ? $s['pointage']['jours'] : round((float) ($c->jours_par_mois ?: 26) * $t['PRORATA'], 2),
            'lignes' => $s['lignes'],
            'totaux' => $t,
            'brut' => $t['BRUT'],
            'brut_imposable' => $t['BRUT_IMPOSABLE'],
            'cotisations_salariales' => $t['COTISATIONS_SALARIALES'],
            'cotisations_patronales' => $t['COTISATIONS_PATRONALES'],
            'impots' => $t['IMPOTS'],
            'retenues' => $t['RETENUES'],
            'net_a_payer' => $t['NET'],
            'cout_employeur' => $t['COUT'],
            'statut' => 'calcule',
        ])->save();

        return $bulletin;
    }

    /**
     * Contrats actifs pendant la période (entrés avant la fin, non sortis avant le début).
     */
    public function contrats(PeriodePaie $p)
    {
        return Contrat::with('employe')->where('statut', 'actif')->whereDate('date_debut', '<=', $p->date_fin)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $p->date_debut))
            ->whereHas('employe', fn ($e) => $e->where('statut', '!=', 'sorti')->orWhereDate('date_sortie', '>=', $p->date_debut))
            ->get();
    }

    public function calculerTout(PeriodePaie $p): int
    {
        $contrats = $this->contrats($p);
        if ($contrats->isEmpty()) {
            throw new DomainException('Aucun contrat actif sur la période : créez d\'abord les contrats du personnel.');
        }

        DB::transaction(function () use ($p, $contrats) {
            foreach ($contrats as $c) {
                $this->calculer($c, $p);
            }
            $this->totaliser($p);
            $p->update(['statut' => 'calculee']);
        });

        return $contrats->count();
    }

    /**
     * Validation : bulletins figés, avances remboursées, écriture de paie passée.
     */
    public function valider(PeriodePaie $p): void
    {
        if ($p->statut !== 'calculee') {
            throw new DomainException('Calculez la paie avant de la valider.');
        }

        DB::transaction(function () use ($p) {
            foreach ($p->bulletins()->where('statut', 'calcule')->get() as $b) {
                $rembourse = (float) collect($b->lignes)->where('code', 'AVANCE')->sum('montant');
                foreach (Avance::where('employe_id', $b->employe_id)->where('statut', 'en_cours')->orderBy('date_octroi')->get() as $a) {
                    if ($rembourse <= 0) {
                        break;
                    }
                    $part = min($rembourse, (float) $a->montant_echeance, $a->reste());
                    $a->montant_rembourse = round((float) $a->montant_rembourse + $part, 2);
                    $a->statut = $a->reste() <= 0.009 ? 'soldee' : 'en_cours';
                    $a->save();
                    $rembourse -= $part;
                }
                $b->update(['statut' => 'valide']);
            }

            $p->update(['statut' => 'validee', 'valide_par' => auth()->id(), 'valide_le' => now()]);
            $this->compta->tenter(fn () => $this->compta->paie($p));
        });
    }

    public function payer(Bulletin $b, array $donnees): PaiementSalaire
    {
        if (!in_array($b->statut, ['valide', 'partiel'], true)) {
            throw new DomainException('Seul un bulletin validé peut être payé.');
        }
        $montant = round((float) ($donnees['montant'] ?? $b->resteAPayer()), 2);
        if ($montant <= 0 || $montant > $b->resteAPayer() + 0.009) {
            throw new DomainException('Le montant doit être compris entre 0 et le reste à payer (' . montant($b->resteAPayer()) . ').');
        }

        return DB::transaction(function () use ($b, $donnees, $montant) {
            $paiement = PaiementSalaire::create([
                'numero' => $this->numerotation->suivant('paiement_salaire'),
                'bulletin_id' => $b->id,
                'employe_id' => $b->employe_id,
                'date_paiement' => $donnees['date_paiement'] ?? today(),
                'montant' => $montant,
                'mode_paiement' => $donnees['mode_paiement'],
                'reference' => $donnees['reference'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $b->montant_paye = round((float) $b->montant_paye + $montant, 2);
            $b->statut = $b->resteAPayer() <= 0.009 ? 'paye' : 'partiel';
            $b->save();

            $periode = $b->periode;
            if ($periode->bulletins()->whereNotIn('statut', ['paye', 'annule'])->doesntExist()) {
                $periode->update(['statut' => 'payee']);
            }

            $this->compta->tenter(fn () => $this->compta->paiementSalaire($paiement));

            return $paiement;
        });
    }

    public function totaliser(PeriodePaie $p): void
    {
        $bulletins = $p->bulletins()->where('statut', '!=', 'annule');
        $p->update(['total_brut' => (clone $bulletins)->sum('brut'), 'total_net' => (clone $bulletins)->sum('net_a_payer'), 'total_cout' => (clone $bulletins)->sum('cout_employeur')]);
    }
}
