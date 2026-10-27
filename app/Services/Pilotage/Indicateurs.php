<?php

namespace App\Services\Pilotage;

use App\Enums\StatutBien;
use App\Models\AppelLigne;
use App\Models\Bail;
use App\Models\Bien;
use App\Models\Depense;
use App\Models\Echeance;
use App\Models\Encaissement;
use App\Models\Loyer;
use App\Models\Objectif;
use App\Models\Prospect;
use App\Models\Referentiel;
use App\Models\RendezVous;
use App\Models\Tiers;
use App\Models\User;
use App\Models\Vente;
use App\Support\AgenceCourante;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs et états de pilotage (remplace les 15 états de TableaudebordController et ses vues SQL).
 * Chaque état renvoie : titre, colonnes, lignes et éventuellement un graphique.
 */
class Indicateurs
{
    public const RAPPORTS = [
        'ca-ventes' => ['Chiffre d\'affaires des ventes', 'ti-home-dollar', 'vente'],
        'encaissements' => ['Encaissements par activité et mode', 'ti-cash', 'finance'],
        'impayes' => ['Balance âgée des impayés', 'ti-alert-triangle', 'finance'],
        'stock' => ['État du patrimoine', 'ti-building-estate', 'patrimoine'],
        'occupation' => ['Occupation locative', 'ti-key', 'location'],
        'commerciaux' => ['Performance commerciale et objectifs', 'ti-target-arrow', 'vente'],
        'canaux' => ['Canaux d\'acquisition', 'ti-speakerphone', 'tiers'],
        'rendezvous' => ['Rendez-vous par agent', 'ti-calendar', 'tiers'],
        'vente-echeancier' => ['Échéancier des ventes à venir', 'ti-calendar-dollar', 'vente'],
        'reversements' => ['Reversements par propriétaire', 'ti-building-bank', 'location'],
        'syndic-situation' => ['Situation des copropriétaires', 'ti-building-community', 'syndic'],
        'syndic-budget' => ['Budget voté et dépenses réelles', 'ti-chart-pie', 'syndic'],
        'meuble-occupation' => ['Occupation des résidences meublées', 'ti-bed', 'meuble'],
        'tresorerie' => ['Flux de trésorerie mensuels', 'ti-arrows-exchange', 'finance'],
        'recouvrement' => ['Portefeuille de recouvrement', 'ti-alarm', 'recouvrement'],
        'relances' => ['Journal des relances', 'ti-send', 'recouvrement'],
        'rh-effectif' => ['Effectif et absentéisme', 'ti-users-group', 'rh'],
        'paie-masse' => ['Masse salariale mensuelle', 'ti-report-money', 'paie'],
    ];

    public function __construct(protected AgenceCourante $agenceCourante)
    {
    }

    /**
     * @return array<string, array> rapports disponibles selon les modules actifs
     */
    public function disponibles(): array
    {
        return array_filter(self::RAPPORTS, fn ($r) => $this->agenceCourante->moduleActif($r[2]));
    }

    public function rapport(string $code, Carbon $du, Carbon $au): array
    {
        $methode = 'rapport' . str_replace(' ', '', ucwords(str_replace('-', ' ', $code)));

        return ['titre' => self::RAPPORTS[$code][0]] + $this->{$methode}($du, $au);
    }

    /**
     * Tuiles du tableau de bord.
     */
    public function tableauDeBord(): array
    {
        $agence = $this->agenceCourante;
        $debutMois = today()->startOfMonth();
        $tuiles = [];

        $tuiles[] = ['Encaissé ce mois', montant(Encaissement::valides()->where('date_encaissement', '>=', $debutMois)->sum('montant')), 'ti-cash', route('encaissements.index', ['du' => $debutMois->toDateString()])];

        if ($agence->moduleActif('vente')) {
            $tuiles[] = ['Ventes validées ce mois', montant(Vente::whereNotNull('valide_le')->where('valide_le', '>=', $debutMois)->sum('montant_net')), 'ti-home-dollar', route('ventes.index')];
            $tuiles[] = ['Échéances en retard', montant(Echeance::enRetard()->whereHas('vente', fn ($q) => $q->where('statut', 'en_cours'))->selectRaw('coalesce(sum(montant - montant_paye),0) t')->value('t')), 'ti-alert-triangle', route('echeances.index')];
        }
        if ($agence->moduleActif('location')) {
            $tuiles[] = ['Loyers impayés', montant(Loyer::enRetard()->selectRaw('coalesce(sum(montant - montant_paye),0) t')->value('t')), 'ti-key', route('loyers.index', ['filtre' => 'impayes'])];
            $aLouer = Bien::where('a_louer', true)->count();
            $tuiles[] = ['Taux d\'occupation', ($aLouer ? round(Bien::where('a_louer', true)->where('statut', StatutBien::Loue->value)->count() / $aLouer * 100) : 0) . ' %', 'ti-building', route('etats.show', 'occupation')];
        }
        if ($agence->moduleActif('syndic')) {
            $tuiles[] = ['Charges de copropriété dues', montant(AppelLigne::enRetard()->selectRaw('coalesce(sum(montant - montant_paye),0) t')->value('t')), 'ti-building-community', route('appelsfonds.index')];
        }

        $tuiles[] = ['Biens disponibles', Bien::disponibles()->count() . ' / ' . Bien::count(), 'ti-building-estate', route('biens.index', ['statut' => 'disponible'])];
        $tuiles[] = ['Prospects actifs', Prospect::whereNotIn('statut', ['converti', 'perdu'])->count(), 'ti-users', route('prospects.index')];

        return $tuiles;
    }

    /**
     * Encaissements des 12 derniers mois (graphique du tableau de bord).
     */
    public function encaissementsMensuels(int $mois = 12): array
    {
        $debut = today()->startOfMonth()->subMonths($mois - 1);
        $lignes = Encaissement::valides()->where('date_encaissement', '>=', $debut)
            ->selectRaw("to_char(date_encaissement, 'YYYY-MM') as mois, activite, sum(montant) as total")
            ->groupBy('mois', 'activite')->get();

        $libelles = collect(range(0, $mois - 1))->map(fn ($i) => $debut->copy()->addMonths($i));
        $series = [];
        foreach ($lignes->groupBy('activite') as $activite => $valeurs) {
            $parMois = $valeurs->pluck('total', 'mois');
            $series[Encaissement::ACTIVITES[$activite] ?? $activite] = $libelles->map(fn ($m) => (float) ($parMois[$m->format('Y-m')] ?? 0))->all();
        }

        return ['libelles' => $libelles->map(fn ($m) => $m->locale('fr')->translatedFormat('M y'))->all(), 'series' => $series];
    }

    protected function rapportCaVentes(Carbon $du, Carbon $au): array
    {
        $ventes = Vente::whereNotNull('valide_le')->whereBetween('date_contrat', [$du, $au])
            ->selectRaw("to_char(date_contrat, 'YYYY-MM') as mois, count(*) as nombre, sum(montant_net) as ca, sum(montant_frais) as frais")
            ->groupBy('mois')->orderBy('mois')->get();
        $encaisse = Encaissement::valides()->where('activite', 'vente')->whereBetween('date_encaissement', [$du, $au])
            ->selectRaw("to_char(date_encaissement, 'YYYY-MM') as mois, sum(montant) as total")->groupBy('mois')->pluck('total', 'mois');

        $lignes = $ventes->map(fn ($v) => [Carbon::parse($v->mois . '-01')->locale('fr')->translatedFormat('F Y'), $v->nombre, (float) $v->ca, (float) $v->frais, (float) ($encaisse[$v->mois] ?? 0)])->all();

        return [
            'colonnes' => ['Mois', 'Contrats', 'Chiffre d\'affaires', 'Frais annexes', 'Encaissé'],
            'montants' => [2, 3, 4],
            'lignes' => $lignes,
            'total' => ['Total', array_sum(array_column($lignes, 1)), array_sum(array_column($lignes, 2)), array_sum(array_column($lignes, 3)), array_sum(array_column($lignes, 4))],
            'graphique' => ['type' => 'bar', 'libelles' => array_column($lignes, 0), 'series' => ['Chiffre d\'affaires' => array_column($lignes, 2), 'Encaissé' => array_column($lignes, 4)]],
        ];
    }

    protected function rapportEncaissements(Carbon $du, Carbon $au): array
    {
        $modes = Referentiel::options('mode_paiement');
        $donnees = Encaissement::valides()->whereBetween('date_encaissement', [$du, $au])
            ->selectRaw('activite, mode_paiement, count(*) as nombre, sum(montant) as total')->groupBy('activite', 'mode_paiement')->get();

        $lignes = $donnees->map(fn ($d) => [Encaissement::ACTIVITES[$d->activite] ?? $d->activite, $modes[$d->mode_paiement] ?? $d->mode_paiement, $d->nombre, (float) $d->total])->all();
        $parActivite = $donnees->groupBy('activite')->map->sum('total');

        return [
            'colonnes' => ['Activité', 'Mode de paiement', 'Reçus', 'Montant'],
            'montants' => [3],
            'lignes' => $lignes,
            'total' => ['Total', '', array_sum(array_column($lignes, 2)), array_sum(array_column($lignes, 3))],
            'graphique' => ['type' => 'doughnut', 'libelles' => $parActivite->keys()->map(fn ($a) => Encaissement::ACTIVITES[$a] ?? $a)->all(), 'series' => ['Montant' => $parActivite->values()->map(fn ($v) => (float) $v)->all()]],
        ];
    }

    /**
     * Balance âgée : reste dû par tranche de retard, pour chaque activité.
     */
    protected function rapportImpayes(Carbon $du, Carbon $au): array
    {
        $tranches = ['0-30 j' => [0, 30], '31-60 j' => [31, 60], '61-90 j' => [61, 90], '> 90 j' => [91, 100000]];
        $sources = [
            'Ventes (échéances)' => Echeance::query()->join('ventes', 'ventes.id', '=', 'echeances.vente_id')->where('ventes.statut', 'en_cours')
                ->whereIn('echeances.statut', ['a_payer', 'partiel'])->where('echeances.date_echeance', '<', today())
                ->select(DB::raw('echeances.date_echeance as echeance'), DB::raw('echeances.montant - echeances.montant_paye as reste')),
            'Location (loyers)' => Loyer::query()->whereIn('statut', ['a_payer', 'partiel'])->where('date_echeance', '<', today())
                ->select(DB::raw('date_echeance as echeance'), DB::raw('montant - montant_paye as reste')),
            'Syndic (appels de fonds)' => AppelLigne::query()->join('appels_fonds', 'appels_fonds.id', '=', 'appel_lignes.appel_fonds_id')
                ->whereIn('appel_lignes.statut', ['a_payer', 'partiel'])->where('appels_fonds.date_echeance', '<', today())
                ->select(DB::raw('appels_fonds.date_echeance as echeance'), DB::raw('appel_lignes.montant - appel_lignes.montant_paye as reste')),
        ];

        $lignes = [];
        foreach ($sources as $libelle => $query) {
            if (!$this->agenceCourante->moduleActif(['Ventes (échéances)' => 'vente', 'Location (loyers)' => 'location', 'Syndic (appels de fonds)' => 'syndic'][$libelle])) {
                continue;
            }
            $dettes = $query->get();
            $ligne = [$libelle];
            foreach ($tranches as [$min, $max]) {
                $ligne[] = (float) $dettes->filter(function ($d) use ($min, $max) {
                    $jours = (int) Carbon::parse($d->echeance)->diffInDays(today());

                    return $jours >= $min && $jours <= $max;
                })->sum('reste');
            }
            $ligne[] = array_sum(array_slice($ligne, 1));
            $lignes[] = $ligne;
        }

        $total = ['Total'];
        foreach (range(1, count($tranches) + 1) as $i) {
            $total[] = array_sum(array_column($lignes, $i));
        }

        return [
            'colonnes' => array_merge(['Activité'], array_keys($tranches), ['Total']),
            'montants' => range(1, count($tranches) + 1),
            'lignes' => $lignes,
            'total' => $total,
            'graphique' => ['type' => 'bar', 'libelles' => array_keys($tranches), 'series' => collect($lignes)->mapWithKeys(fn ($l) => [$l[0] => array_slice($l, 1, count($tranches))])->all()],
            'sansPeriode' => true,
        ];
    }

    protected function rapportStock(Carbon $du, Carbon $au): array
    {
        $types = Referentiel::options('type_bien');
        $donnees = Bien::selectRaw('type_bien, statut, count(*) as nombre, sum(coalesce(prix_vente, 0)) as valeur')->groupBy('type_bien', 'statut')->get();

        $lignes = [];
        foreach ($donnees->groupBy('type_bien') as $type => $parStatut) {
            $ligne = [$types[$type] ?? $type];
            foreach (StatutBien::cases() as $statut) {
                $ligne[] = (int) ($parStatut->firstWhere('statut', $statut)?->nombre ?? 0);
            }
            $ligne[] = array_sum(array_slice($ligne, 1));
            $ligne[] = (float) $parStatut->where('statut', StatutBien::Disponible)->sum('valeur');
            $lignes[] = $ligne;
        }

        $nbStatuts = count(StatutBien::cases());
        $total = ['Total'];
        foreach (range(1, $nbStatuts + 2) as $i) {
            $total[] = array_sum(array_column($lignes, $i));
        }

        return [
            'colonnes' => array_merge(['Type'], array_map(fn ($s) => $s->libelle(), StatutBien::cases()), ['Total', 'Valeur du stock disponible']),
            'montants' => [$nbStatuts + 2],
            'lignes' => $lignes,
            'total' => $total,
            'graphique' => ['type' => 'doughnut', 'libelles' => array_map(fn ($s) => $s->libelle(), StatutBien::cases()), 'series' => ['Biens' => array_slice($total, 1, $nbStatuts)]],
            'sansPeriode' => true,
        ];
    }

    protected function rapportOccupation(Carbon $du, Carbon $au): array
    {
        $lignes = Bien::with(['proprietaire', 'bailActif.locataire'])->where('a_louer', true)->orderBy('reference')->get()
            ->map(fn ($b) => [
                $b->designation,
                $b->proprietaire?->nom_complet ?? 'Agence',
                $b->bailActif?->locataire?->nom_complet ?? '—',
                (float) ($b->bailActif?->loyer_mensuel ?? $b->loyer_mensuel),
                $b->bailActif ? (float) $b->bailActif->soldeDu() : 0,
                $b->statut->libelle(),
            ])->all();

        $loues = collect($lignes)->where(5, StatutBien::Loue->libelle())->count();

        return [
            'colonnes' => ['Bien', 'Propriétaire', 'Locataire', 'Loyer', 'Solde dû', 'Statut'],
            'montants' => [3, 4],
            'lignes' => $lignes,
            'total' => ['Taux d\'occupation : ' . (count($lignes) ? round($loues / count($lignes) * 100, 1) : 0) . ' %', '', '', array_sum(array_column($lignes, 3)), array_sum(array_column($lignes, 4)), ''],
            'sansPeriode' => true,
        ];
    }

    protected function rapportCommerciaux(Carbon $du, Carbon $au): array
    {
        $users = User::where('agence_id', $this->agenceCourante->id())->orderBy('name')->get();

        $lignes = $users->map(function ($u) use ($du, $au) {
            $ca = (float) Vente::where('commercial_id', $u->id)->whereNotNull('valide_le')->whereBetween('date_contrat', [$du, $au])->sum('montant_net');
            $objectif = (float) Objectif::where('user_id', $u->id)->where('periode_debut', '<=', $au)->where('periode_fin', '>=', $du)->sum('montant_objectif');

            return [
                $u->nom_complet,
                Prospect::where('commercial_id', $u->id)->whereBetween('created_at', [$du, $au->copy()->endOfDay()])->count(),
                RendezVous::where('user_id', $u->id)->whereBetween('debut', [$du, $au->copy()->endOfDay()])->where('statut', 'realise')->count(),
                Vente::where('commercial_id', $u->id)->whereNotNull('valide_le')->whereBetween('date_contrat', [$du, $au])->count(),
                $ca,
                $objectif,
                $objectif > 0 ? round($ca / $objectif * 100, 1) . ' %' : '—',
            ];
        })->filter(fn ($l) => $l[1] || $l[2] || $l[3] || $l[5])->values()->all();

        return [
            'colonnes' => ['Commercial', 'Prospects', 'RDV réalisés', 'Ventes', 'Chiffre d\'affaires', 'Objectif', 'Réalisation'],
            'montants' => [4, 5],
            'lignes' => $lignes,
            'graphique' => ['type' => 'bar', 'libelles' => array_column($lignes, 0), 'series' => ['Chiffre d\'affaires' => array_column($lignes, 4), 'Objectif' => array_column($lignes, 5)]],
        ];
    }

    protected function rapportCanaux(Carbon $du, Carbon $au): array
    {
        $canaux = Referentiel::options('canal_acquisition');
        $donnees = Tiers::whereBetween('created_at', [$du, $au->copy()->endOfDay()])
            ->selectRaw("coalesce(canal_acquisition, 'NON_RENSEIGNE') as canal, count(*) as nombre")->groupBy('canal')->get();
        $convertis = Tiers::whereBetween('created_at', [$du, $au->copy()->endOfDay()])->avecRole('acquereur', 'locataire', 'client')
            ->selectRaw("coalesce(canal_acquisition, 'NON_RENSEIGNE') as canal, count(*) as nombre")->groupBy('canal')->pluck('nombre', 'canal');

        $lignes = $donnees->map(fn ($d) => [
            $canaux[$d->canal] ?? 'Non renseigné', $d->nombre, (int) ($convertis[$d->canal] ?? 0),
            $d->nombre ? round(($convertis[$d->canal] ?? 0) / $d->nombre * 100, 1) . ' %' : '—',
        ])->sortByDesc(1)->values()->all();

        return [
            'colonnes' => ['Canal', 'Nouveaux contacts', 'Devenus clients', 'Conversion'],
            'montants' => [],
            'lignes' => $lignes,
            'graphique' => ['type' => 'doughnut', 'libelles' => array_column($lignes, 0), 'series' => ['Contacts' => array_column($lignes, 1)]],
        ];
    }

    protected function rapportRendezvous(Carbon $du, Carbon $au): array
    {
        $donnees = RendezVous::with('agent')->whereBetween('debut', [$du, $au->copy()->endOfDay()])->get()->groupBy('user_id');

        $lignes = $donnees->map(fn ($rdvs) => [
            $rdvs->first()->agent?->nom_complet ?? 'Non attribué',
            $rdvs->count(),
            $rdvs->where('statut.value', 'realise')->count(),
            $rdvs->where('statut.value', 'reporte')->count(),
            $rdvs->where('statut.value', 'annule')->count(),
            $rdvs->where('source', 'vitrine')->count(),
        ])->values()->all();

        return [
            'colonnes' => ['Agent', 'Rendez-vous', 'Réalisés', 'Reportés', 'Annulés', 'Demandés via le site'],
            'montants' => [],
            'lignes' => $lignes,
        ];
    }

    // ------------------------------------------------------------------ états par module

    protected function totaux(array $lignes, int $depuis, string $libelle = 'Total'): array
    {
        $nb = count($lignes[0] ?? []);
        $total = [$libelle];
        for ($i = 1; $i < $nb; $i++) {
            $total[] = $i < $depuis ? '' : array_sum(array_map(fn ($l) => is_numeric($l[$i]) ? $l[$i] : 0, $lignes));
        }

        return $total;
    }

    protected function rapportVenteEcheancier(Carbon $du, Carbon $au): array
    {
        $lignes = Echeance::impayes()->whereHas('vente', fn ($q) => $q->where('statut', 'en_cours'))->whereDate('date_echeance', '>=', today()->startOfMonth())
            ->selectRaw("to_char(date_echeance, 'YYYY-MM') as mois, count(*) as nombre, sum(montant) as du, sum(montant_paye) as paye")
            ->groupBy('mois')->orderBy('mois')->limit(24)->get()
            ->map(fn ($l) => [ucfirst(Carbon::parse($l->mois . '-01')->locale('fr')->translatedFormat('F Y')), $l->nombre, (float) $l->du, (float) $l->paye, (float) $l->du - (float) $l->paye])->all();

        return ['colonnes' => ['Mois', 'Échéances', 'Montant', 'Déjà payé', 'Reste à encaisser'], 'montants' => [2, 3, 4], 'lignes' => $lignes,
            'total' => $this->totaux($lignes, 1), 'sansPeriode' => true,
            'graphique' => ['type' => 'bar', 'libelles' => array_column($lignes, 0), 'series' => ['Reste à encaisser' => array_column($lignes, 4)]]];
    }

    protected function rapportReversements(Carbon $du, Carbon $au): array
    {
        $lignes = \App\Models\Reversement::with('proprietaire')->whereBetween('periode_fin', [$du->toDateString(), $au->toDateString()])->get()->groupBy('proprietaire_id')
            ->map(fn ($r) => [$r->first()->proprietaire?->nom_complet, $r->count(), (float) $r->sum('montant_encaisse'), (float) $r->sum('montant_commission'),
                (float) $r->sum('montant_deductions'), (float) $r->sum('montant_net'), (float) $r->where('statut.value', 'paye')->sum('montant_net')])->values()->all();

        return ['colonnes' => ['Propriétaire', 'Reversements', 'Loyers encaissés', 'Commissions', 'Dépenses', 'Net', 'Déjà payé'], 'montants' => [2, 3, 4, 5, 6],
            'lignes' => $lignes, 'total' => $this->totaux($lignes, 1)];
    }

    protected function rapportSyndicSituation(Carbon $du, Carbon $au): array
    {
        $lignes = \App\Models\AppelLigne::with(['coproprietaire', 'appel.copropriete', 'lot'])->where('statut', '!=', 'annule')
            ->whereHas('appel', fn ($q) => $q->whereBetween('date_appel', [$du->toDateString(), $au->toDateString()]))->get()
            ->groupBy(fn ($l) => $l->appel->copropriete_id . '|' . $l->coproprietaire_id)
            ->map(fn ($g) => [$g->first()->appel->copropriete->nom, $g->first()->coproprietaire?->nom_complet ?? 'Lot ' . $g->first()->lot->numero,
                $g->pluck('lot.numero')->unique()->implode(', '), (float) $g->sum('montant'), (float) $g->sum('montant_paye'), (float) $g->sum('montant') - (float) $g->sum('montant_paye')])
            ->sortBy([[0, 'asc'], [5, 'desc']])->values()->all();

        return ['colonnes' => ['Copropriété', 'Copropriétaire', 'Lots', 'Appelé', 'Payé', 'Reste dû'], 'montants' => [3, 4, 5], 'lignes' => $lignes, 'total' => $this->totaux($lignes, 3)];
    }

    protected function rapportSyndicBudget(Carbon $du, Carbon $au): array
    {
        $postes = Referentiel::options('categorie_depense');
        $lignes = [];
        foreach (\App\Models\Budget::with(['lignes', 'copropriete'])->where('exercice', $au->year)->get() as $budget) {
            $reel = Depense::where('copropriete_id', $budget->copropriete_id)->where('statut', 'payee')
                ->whereBetween('date_depense', [$budget->date_debut, $budget->date_fin])->selectRaw('categorie, sum(montant) as t')->groupBy('categorie')->pluck('t', 'categorie');
            foreach ($budget->lignes->groupBy('poste') as $poste => $l) {
                $prevu = (float) $l->sum('montant');
                $fait = (float) ($reel[$poste] ?? 0);
                $lignes[] = [$budget->copropriete->nom . ' — ' . ($postes[$poste] ?? $poste), $prevu, $fait, $prevu - $fait, $prevu > 0 ? round($fait / $prevu * 100, 1) . ' %' : '—'];
            }
        }

        return ['colonnes' => ['Copropriété — poste (exercice ' . $au->year . ')', 'Budget voté', 'Dépensé', 'Écart', 'Consommé'], 'montants' => [1, 2, 3], 'lignes' => $lignes,
            'total' => $this->totaux($lignes, 1), 'sansPeriode' => true];
    }

    protected function rapportMeubleOccupation(Carbon $du, Carbon $au): array
    {
        $nuitsPeriode = max(1, (int) $du->diffInDays($au) + 1);
        $lignes = Bien::where('meuble', true)->orderBy('reference')->get()->map(function ($b) use ($du, $au, $nuitsPeriode) {
            $sejours = \App\Models\Sejour::where('bien_id', $b->id)->where('statut', '!=', 'annule')->where('date_arrivee', '<', $au)->where('date_depart', '>', $du)->get();
            $nuits = $sejours->sum(fn ($s) => (int) $s->date_arrivee->max($du)->diffInDays($s->date_depart->min($au->copy()->addDay())));
            $revenu = (float) $sejours->sum('montant');

            return [$b->reference . ' — ' . $b->libelle, $sejours->count(), $nuits, round($nuits / $nuitsPeriode * 100, 1) . ' %', $revenu, round($revenu / $nuitsPeriode, 2)];
        })->all();

        return ['colonnes' => ['Bien', 'Séjours', 'Nuits occupées', 'Taux d\'occupation', 'Chiffre d\'affaires', 'Revenu par nuit disponible'], 'montants' => [4, 5],
            'lignes' => $lignes, 'total' => $this->totaux($lignes, 1)];
    }

    protected function rapportTresorerie(Carbon $du, Carbon $au): array
    {
        $entrees = Encaissement::valides()->whereBetween('date_encaissement', [$du->toDateString(), $au->toDateString()])
            ->selectRaw("to_char(date_encaissement, 'YYYY-MM') as m, sum(montant) as t")->groupBy('m')->pluck('t', 'm');
        $sorties = Depense::where('statut', 'payee')->whereBetween('date_depense', [$du->toDateString(), $au->toDateString()])
            ->selectRaw("to_char(date_depense, 'YYYY-MM') as m, sum(montant) as t")->groupBy('m')->pluck('t', 'm');
        $salaires = \App\Models\Rh\PaiementSalaire::where('statut', 'valide')->whereBetween('date_paiement', [$du->toDateString(), $au->toDateString()])
            ->selectRaw("to_char(date_paiement, 'YYYY-MM') as m, sum(montant) as t")->groupBy('m')->pluck('t', 'm');
        $reversements = \App\Models\Reversement::where('statut', 'paye')->whereBetween('paye_le', [$du->copy()->startOfDay(), $au->copy()->endOfDay()])
            ->selectRaw("to_char(paye_le, 'YYYY-MM') as m, sum(montant_net) as t")->groupBy('m')->pluck('t', 'm');

        $lignes = [];
        $cumul = 0.0;
        for ($m = $du->copy()->startOfMonth(); $m->lte($au); $m->addMonth()) {
            $k = $m->format('Y-m');
            $net = (float) ($entrees[$k] ?? 0) - (float) ($sorties[$k] ?? 0) - (float) ($salaires[$k] ?? 0) - (float) ($reversements[$k] ?? 0);
            $cumul += $net;
            $lignes[] = [ucfirst($m->locale('fr')->translatedFormat('F Y')), (float) ($entrees[$k] ?? 0), (float) ($sorties[$k] ?? 0), (float) ($salaires[$k] ?? 0),
                (float) ($reversements[$k] ?? 0), $net, $cumul];
        }
        $total = $this->totaux($lignes, 1);
        $total[6] = $cumul;

        return ['colonnes' => ['Mois', 'Encaissements', 'Dépenses', 'Salaires versés', 'Reversements', 'Flux net', 'Cumul'], 'montants' => [1, 2, 3, 4, 5, 6], 'lignes' => $lignes,
            'total' => $total, 'graphique' => ['type' => 'bar', 'libelles' => array_column($lignes, 0), 'series' => ['Encaissements' => array_column($lignes, 1), 'Flux net' => array_column($lignes, 5)]]];
    }

    protected function rapportRecouvrement(Carbon $du, Carbon $au): array
    {
        $lignes = \App\Models\Recouvrement::selectRaw('activite, statut, count(*) as n, sum(montant_du) as t')->groupBy('activite', 'statut')->get()
            ->map(fn ($l) => [\App\Models\Recouvrement::ACTIVITES[$l->activite] ?? $l->activite, \App\Models\Recouvrement::STATUTS[$l->statut][0] ?? $l->statut, $l->n, (float) $l->t])
            ->sortBy([[0, 'asc'], [3, 'desc']])->values()->all();

        return ['colonnes' => ['Activité', 'Statut', 'Dossiers', 'Montant dû'], 'montants' => [3], 'lignes' => $lignes, 'total' => $this->totaux($lignes, 2), 'sansPeriode' => true];
    }

    protected function rapportRelances(Carbon $du, Carbon $au): array
    {
        $canaux = \App\Models\RegleRelance::CANAUX;
        $lignes = \App\Models\Relance::with('recouvrement.tiers')->whereBetween('created_at', [$du->copy()->startOfDay(), $au->copy()->endOfDay()])->latest()->limit(1000)->get()
            ->map(fn ($r) => [$r->created_at->format('d/m/Y'), $r->recouvrement->tiers->nom_complet, 'Niveau ' . $r->niveau, $canaux[$r->canal] ?? $r->canal,
                $r->automatique ? 'Automatique' : 'Manuelle', $r->statut, (float) $r->montant_du])->all();

        return ['colonnes' => ['Date', 'Débiteur', 'Niveau', 'Canal', 'Mode', 'Statut', 'Montant dû'], 'montants' => [6], 'lignes' => $lignes];
    }

    protected function rapportRhEffectif(Carbon $du, Carbon $au): array
    {
        $departements = Referentiel::options('departement');
        $lignes = \App\Models\Rh\Employe::with('contratActif')->where('statut', '!=', 'sorti')->get()->groupBy('departement')->map(function ($g, $dep) use ($du, $au) {
            $absences = (float) \App\Models\Rh\Absence::whereIn('employe_id', $g->pluck('id'))->where('statut', 'validee')
                ->whereBetween('date_debut', [$du->toDateString(), $au->toDateString()])->sum('nombre_jours');

            return [$departements[$dep] ?? ($dep ?: 'Non affecté'), $g->count(), $g->where('sexe', 'F')->count(), $g->where('sexe', 'M')->count(),
                round($g->avg(fn ($e) => $e->ancienneteAnnees()), 1), $absences, (float) $g->sum(fn ($e) => (float) $e->contratActif?->salaire_base)];
        })->values()->all();

        return ['colonnes' => ['Département', 'Effectif', 'Femmes', 'Hommes', 'Ancienneté moyenne (ans)', 'Jours d\'absence', 'Salaires de base'], 'montants' => [6],
            'lignes' => $lignes, 'total' => $this->totaux($lignes, 1)];
    }

    protected function rapportPaieMasse(Carbon $du, Carbon $au): array
    {
        $lignes = \App\Models\Rh\PeriodePaie::withCount('bulletins')->whereBetween('date_debut', [$du->copy()->startOfMonth()->toDateString(), $au->toDateString()])->orderBy('date_debut')->get()
            ->map(fn ($p) => [$p->libelle, $p->bulletins_count, (float) $p->total_brut, (float) $p->total_net, (float) $p->total_cout - (float) $p->total_brut, (float) $p->total_cout])->all();

        return ['colonnes' => ['Période', 'Bulletins', 'Brut', 'Net', 'Charges patronales', 'Coût employeur'], 'montants' => [2, 3, 4, 5], 'lignes' => $lignes,
            'total' => $this->totaux($lignes, 2), 'graphique' => ['type' => 'bar', 'libelles' => array_column($lignes, 0), 'series' => ['Coût employeur' => array_column($lignes, 5)]]];
    }
}
