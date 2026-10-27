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
use App\Models\Programme;
use App\Models\Prospect;
use App\Models\Recouvrement;
use App\Models\Reservation;
use App\Models\Rh\Bulletin;
use App\Models\TiersPiece;
use App\Models\Vente;
use App\Services\Compta\ComptaService;
use App\Services\Parametres;
use App\Support\AgenceCourante;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Informatique décisionnelle : indicateurs comparés à la période précédente, tendances, prévisions,
 * risque clients, performance des programmes et des biens, recommandations et simulations.
 */
class AnalyseService
{
    public Carbon $du;

    public Carbon $au;

    public function __construct(protected AgenceCourante $agenceCourante, protected Parametres $parametres, protected ComptaService $compta)
    {
        $this->du = today()->startOfYear();
        $this->au = today();
    }

    public function periode(Carbon $du, Carbon $au): static
    {
        $this->du = $du->copy()->startOfDay();
        $this->au = $au->copy()->endOfDay();

        return $this;
    }

    /** même durée, juste avant la période */
    public function precedente(): array
    {
        $jours = $this->du->diffInDays($this->au) + 1;

        return [$this->du->copy()->subDays((int) $jours), $this->du->copy()->subDay()->endOfDay()];
    }

    protected function actif(string $module): bool
    {
        return $this->agenceCourante->moduleActif($module);
    }

    // ------------------------------------------------------------------ indicateurs

    protected function mesures(Carbon $du, Carbon $au): array
    {
        $encaisse = (float) Encaissement::valides()->whereBetween('date_encaissement', [$du->toDateString(), $au->toDateString()])->sum('montant');
        $ventes = (float) Vente::whereNotNull('valide_le')->whereBetween('date_contrat', [$du->toDateString(), $au->toDateString()])->sum('montant_net');

        // recouvrement : sommes échues sur la période (ventes, loyers, charges) et part payée
        $du_ = $paye = 0.0;
        foreach ([Echeance::query(), Loyer::query()] as $q) {
            $base = $q->where('statut', '!=', 'annule')->whereBetween('date_echeance', [$du->toDateString(), min($au, now())->toDateString()]);
            $du_ += (float) (clone $base)->sum('montant');
            $paye += (float) (clone $base)->sum('montant_paye');
        }
        $appels = AppelLigne::where('statut', '!=', 'annule')->whereHas('appel', fn ($a) => $a->whereBetween('date_echeance', [$du->toDateString(), min($au, now())->toDateString()]));
        $du_ += (float) (clone $appels)->sum('montant');
        $paye += (float) (clone $appels)->sum('montant_paye');

        $depenses = (float) Depense::where('statut', 'payee')->whereBetween('date_depense', [$du->toDateString(), $au->toDateString()])->sum('montant');
        $masse = (float) Bulletin::where('statut', '!=', 'annule')->whereHas('periode', fn ($p) => $p->whereBetween('date_debut', [$du->toDateString(), $au->toDateString()]))->sum('cout_employeur');
        $prospects = Prospect::whereBetween('created_at', [$du, $au]);

        return [
            'encaissements' => $encaisse,
            'ventes' => $ventes,
            'nb_ventes' => Vente::whereNotNull('valide_le')->whereBetween('date_contrat', [$du->toDateString(), $au->toDateString()])->count(),
            'taux_recouvrement' => $du_ > 0 ? round($paye / $du_ * 100, 1) : null,
            'depenses' => $depenses,
            'masse_salariale' => $masse,
            'conversion' => ($n = (clone $prospects)->count()) ? round((clone $prospects)->where('statut', 'converti')->count() / $n * 100, 1) : null,
            'resultat' => $this->actif('comptabilite') ? $this->compta->etatsFinanciers($du, $au)['resultat'] : $encaisse - $depenses - $masse,
        ];
    }

    /**
     * @return array<string, array{libelle: string, valeur: mixed, precedente: mixed, variation: ?float, format: string, icone: string, sens: int}>
     */
    public function indicateurs(): array
    {
        [$pDu, $pAu] = $this->precedente();
        $n = $this->mesures($this->du, $this->au);
        $p = $this->mesures($pDu, $pAu);
        $jours = max(1, $this->du->diffInDays($this->au) + 1);

        $impayes = (float) Recouvrement::whereNotIn('statut', ['solde', 'abandonne'])->sum('montant_du');
        $aLouer = Bien::where('a_louer', true)->count();
        $loues = Bien::where('a_louer', true)->where('statut', StatutBien::Loue->value)->count();

        $definitions = [
            'encaissements' => ['Encaissements', 'montant', 'ti-cash', 1],
            'ventes' => ['Ventes signées', 'montant', 'ti-home-dollar', 1],
            'taux_recouvrement' => ['Taux de recouvrement des échéances', 'pourcentage', 'ti-receipt-2', 1],
            'depenses' => ['Dépenses', 'montant', 'ti-shopping-cart', -1],
            'masse_salariale' => ['Masse salariale (coût employeur)', 'montant', 'ti-users', -1],
            'resultat' => [$this->actif('comptabilite') ? 'Résultat comptable' : 'Résultat de trésorerie', 'montant', 'ti-scale', 1],
            'conversion' => ['Conversion des prospects', 'pourcentage', 'ti-filter', 1],
        ];

        $resultat = [];
        foreach ($definitions as $cle => [$libelle, $format, $icone, $sens]) {
            $resultat[$cle] = ['libelle' => $libelle, 'valeur' => $n[$cle], 'precedente' => $p[$cle], 'format' => $format, 'icone' => $icone, 'sens' => $sens,
                'variation' => is_numeric($n[$cle]) && is_numeric($p[$cle]) && (float) $p[$cle] != 0.0 ? round(((float) $n[$cle] - (float) $p[$cle]) / abs((float) $p[$cle]) * 100, 1) : null];
        }

        $resultat['impayes'] = ['libelle' => 'Impayés à recouvrer (aujourd\'hui)', 'valeur' => $impayes, 'precedente' => null, 'variation' => null, 'format' => 'montant', 'icone' => 'ti-alarm', 'sens' => -1];
        $resultat['dso'] = ['libelle' => 'Délai moyen d\'encaissement (DSO)', 'valeur' => $n['encaissements'] > 0 ? round($impayes / ($n['encaissements'] / $jours)) . ' j' : '—',
            'precedente' => null, 'variation' => null, 'format' => 'texte', 'icone' => 'ti-clock', 'sens' => -1];
        $resultat['occupation'] = ['libelle' => 'Taux d\'occupation locative', 'valeur' => $aLouer ? round($loues / $aLouer * 100, 1) : null, 'precedente' => null, 'variation' => null,
            'format' => 'pourcentage', 'icone' => 'ti-key', 'sens' => 1];
        $resultat['stock'] = ['libelle' => 'Stock disponible à la vente', 'valeur' => (float) Bien::disponibles()->where('a_vendre', true)->sum('prix_vente'), 'precedente' => null,
            'variation' => null, 'format' => 'montant', 'icone' => 'ti-building-estate', 'sens' => 0];

        return $resultat;
    }

    /**
     * Encaissements, dépenses et ventes signées des 12 derniers mois.
     */
    public function tendance(int $mois = 12): array
    {
        $debut = today()->startOfMonth()->subMonths($mois - 1);
        $libelles = collect(range(0, $mois - 1))->map(fn ($i) => $debut->copy()->addMonths($i));
        $serie = fn ($query, string $date, string $colonne) => $query->where($date, '>=', $debut)
            ->selectRaw("to_char($date, 'YYYY-MM') as m, sum($colonne) as t")->groupBy('m')->pluck('t', 'm');

        $enc = $serie(Encaissement::valides(), 'date_encaissement', 'montant');
        $dep = $serie(Depense::where('statut', 'payee'), 'date_depense', 'montant');
        $ven = $serie(Vente::whereNotNull('valide_le'), 'date_contrat', 'montant_net');

        return [
            'libelles' => $libelles->map(fn ($m) => $m->locale('fr')->translatedFormat('M y'))->all(),
            'series' => [
                'Encaissements' => $libelles->map(fn ($m) => (float) ($enc[$m->format('Y-m')] ?? 0))->all(),
                'Dépenses' => $libelles->map(fn ($m) => (float) ($dep[$m->format('Y-m')] ?? 0))->all(),
                'Ventes signées' => $libelles->map(fn ($m) => (float) ($ven[$m->format('Y-m')] ?? 0))->all(),
            ],
        ];
    }

    /**
     * Taux de recouvrement historique (12 derniers mois) : part des sommes échues effectivement payées.
     */
    public function tauxHistorique(): float
    {
        $mesures = $this->mesures(today()->subYear(), today());

        return ($mesures['taux_recouvrement'] ?? 80) / 100;
    }

    /**
     * Prévision d'encaissement des 6 prochains mois : échéances à venir pondérées par le taux historique.
     */
    public function prevision(int $mois = 6): array
    {
        $taux = $this->tauxHistorique();
        $lignes = [];

        for ($i = 0; $i < $mois; $i++) {
            $debut = today()->startOfMonth()->addMonths($i);
            $fin = $debut->copy()->endOfMonth();
            $periode = [$debut->toDateString(), $fin->toDateString()];

            $ventes = (float) Echeance::impayes()->whereBetween('date_echeance', $periode)->whereHas('vente', fn ($q) => $q->where('statut', 'en_cours'))
                ->selectRaw('coalesce(sum(montant - montant_paye), 0) as r')->value('r');
            $loyers = (float) Loyer::impayes()->whereBetween('date_echeance', $periode)->selectRaw('coalesce(sum(montant - montant_paye), 0) as r')->value('r');
            if ($i > 0 && $this->actif('location')) {
                // loyers pas encore générés : estimés sur les baux actifs
                $loyers = max($loyers, (float) Bail::where('statut', 'actif')->sum('loyer_mensuel') + (float) Bail::where('statut', 'actif')->sum('charges_mensuelles'));
            }
            $charges = (float) AppelLigne::impayes()->whereHas('appel', fn ($q) => $q->where('statut', 'emis')->whereBetween('date_echeance', $periode))
                ->selectRaw('coalesce(sum(montant - montant_paye), 0) as r')->value('r');

            $total = $ventes + $loyers + $charges;
            $lignes[] = ['mois' => ucfirst($debut->locale('fr')->translatedFormat('F Y')), 'ventes' => $ventes, 'loyers' => $loyers, 'charges' => $charges,
                'total' => $total, 'probable' => round($total * $taux, 2)];
        }

        return ['taux' => round($taux * 100, 1), 'lignes' => $lignes, 'arrieres' => (float) Recouvrement::whereNotIn('statut', ['solde', 'abandonne'])->sum('montant_du')];
    }

    /**
     * Débiteurs les plus risqués : retard, montant, niveau de relance, promesses non tenues.
     */
    public function risqueClients(int $limite = 10): Collection
    {
        $dossiers = Recouvrement::with('tiers')->withCount(['promesses as promesses_non_tenues' => fn ($q) => $q->where('statut', 'non_tenue')])
            ->whereNotIn('statut', ['solde', 'abandonne'])->get();
        $max = max(1, (float) $dossiers->max('montant_du'));

        return $dossiers->map(function ($d) use ($max) {
            $score = min(100, round(min(40, $d->joursRetard() / 90 * 40) + (float) $d->montant_du / $max * 30 + $d->niveau * 5 + $d->promesses_non_tenues * 10));

            return ['dossier' => $d, 'score' => $score, 'niveau' => $score >= 70 ? 'danger' : ($score >= 40 ? 'warning' : 'success')];
        })->sortByDesc('score')->take($limite)->values();
    }

    /**
     * Écoulement des programmes : stock, ventes, délai moyen de vente.
     */
    public function programmes(): Collection
    {
        return Programme::withCount([
            'biens', 'biens as vendus' => fn ($q) => $q->where('statut', 'vendu'), 'biens as reserves' => fn ($q) => $q->where('statut', 'reserve'),
            'biens as disponibles' => fn ($q) => $q->where('statut', 'disponible'),
        ])->get()->filter(fn ($p) => $p->biens_count > 0)->map(function ($p) {
            $ventes = Vente::whereNotNull('valide_le')->whereHas('biens', fn ($b) => $b->where('programme_id', $p->id))->get(['montant_net', 'valide_le', 'created_at']);

            return [
                'programme' => $p,
                'ecoulement' => round($p->vendus / max(1, $p->biens_count) * 100, 1),
                'ca' => (float) $ventes->sum('montant_net'),
                'stock' => (float) Bien::where('programme_id', $p->id)->disponibles()->sum('prix_vente'),
                'age_mois' => (int) ($p->date_lancement ?? $p->created_at)->diffInMonths(today()),
            ];
        })->sortBy('ecoulement')->values();
    }

    /**
     * Rentabilité des biens en location sur 12 mois : loyers encaissés, dépenses, rendement brut.
     */
    public function rentabiliteBiens(int $limite = 15): Collection
    {
        $depuis = today()->subYear();

        return Bien::with('proprietaire')->where('a_louer', true)->get()->map(function ($b) use ($depuis) {
            $encaisse = (float) Loyer::whereHas('bail', fn ($q) => $q->where('bien_id', $b->id))->whereIn('nature', ['loyer', 'avance', 'regularisation'])
                ->whereDate('date_echeance', '>=', $depuis)->sum('montant_paye');
            $depenses = (float) Depense::where('bien_id', $b->id)->where('statut', 'payee')->whereDate('date_depense', '>=', $depuis)->sum('montant');
            $impayes = (float) Loyer::whereHas('bail', fn ($q) => $q->where('bien_id', $b->id))->enRetard()->selectRaw('coalesce(sum(montant - montant_paye), 0) as r')->value('r');

            return [
                'bien' => $b, 'encaisse' => $encaisse, 'depenses' => $depenses, 'net' => $encaisse - $depenses, 'impayes' => $impayes,
                'rendement' => (float) $b->prix_vente > 0 ? round((float) $b->loyer_mensuel * 12 / (float) $b->prix_vente * 100, 2) : null,
                'statut' => $b->statut,
            ];
        })->sortByDesc('net')->take($limite)->values();
    }

    /**
     * Recommandations à partir des données (règles de gestion paramétrables par les objectifs de l'agence).
     *
     * @return array<int, array{niveau: string, titre: string, detail: string, action: ?string, url: ?string}>
     */
    public function recommandations(): array
    {
        $r = [];
        $ajouter = fn (string $niveau, string $titre, string $detail, ?string $action = null, ?string $url = null) => $r[] = compact('niveau', 'titre', 'detail', 'action', 'url');
        $seuilRisque = (int) $this->parametres->get('analyse.seuil_risque_jours');

        if ($this->actif('recouvrement') || $this->actif('location') || $this->actif('vente')) {
            $vieux = Recouvrement::whereNotIn('statut', ['solde', 'abandonne'])->whereDate('plus_ancienne_echeance', '<', today()->subDays($seuilRisque));
            if (($n = (clone $vieux)->count()) > 0) {
                $ajouter('danger', "$n débiteur(s) en retard de plus de $seuilRisque jours", 'Montant concerné : ' . montant((clone $vieux)->sum('montant_du'))
                    . '. Adressez une mise en demeure, proposez un échéancier (protocole) ou engagez le contentieux.', 'Ouvrir les dossiers', route('recouvrements.index'));
            }
        }

        if ($this->actif('location')) {
            $locataires = Loyer::with('bail.locataire')->where('nature', 'loyer')->enRetard()->get()->groupBy('bail_id')->filter(fn ($l) => $l->count() >= 3);
            if ($locataires->isNotEmpty()) {
                $ajouter('danger', $locataires->count() . ' bail(s) avec 3 loyers impayés ou plus',
                    'Locataires : ' . $locataires->map(fn ($l) => $l->first()->bail->locataire->nom_complet)->take(5)->implode(', ')
                    . '. Envisagez la résiliation pour impayés ou un protocole, et mobilisez le garant.', 'Voir les impayés', route('loyers.index', ['filtre' => 'impayes']));
            }

            $aLouer = Bien::where('a_louer', true)->count();
            $vacants = Bien::where('a_louer', true)->disponibles()->get();
            $objectif = (float) $this->parametres->get('analyse.objectif_occupation');
            $taux = $aLouer ? ($aLouer - $vacants->count()) / $aLouer * 100 : 100;
            if ($aLouer && $taux < $objectif) {
                $longs = $vacants->filter(fn ($b) => $b->updated_at->lt(now()->subDays(60)))->count();
                $ajouter('warning', 'Occupation locative à ' . round($taux, 1) . ' % (objectif ' . $objectif . ' %)',
                    $vacants->count() . ' bien(s) vacant(s), dont ' . $longs . ' depuis plus de 60 jours : publiez-les sur le site, revoyez le loyer ou proposez des visites.',
                    'Biens vacants', route('biens.index', ['destination' => 'location', 'statut' => 'disponible']));
            }
        }

        if ($this->actif('vente')) {
            foreach ($this->programmes()->filter(fn ($p) => $p['ecoulement'] < 30 && $p['age_mois'] >= 6) as $p) {
                $ajouter('warning', 'Écoulement lent : ' . $p['programme']->nom, 'Seulement ' . $p['ecoulement'] . ' % vendus en ' . $p['age_mois'] . ' mois ('
                    . montant($p['stock']) . ' de stock). Lancez une campagne ciblée, une offre de lancement ou un échéancier plus long.', 'Créer une campagne',
                    $this->actif('marketing') ? route('campagnes.create') : null);
            }
            $relances = Prospect::whereNotIn('statut', ['converti', 'perdu'])->whereDate('date_relance', '<', today())->count();
            if ($relances) {
                $ajouter('info', "$relances prospect(s) à relancer", 'La date de relance est dépassée : contactez-les avant qu\'ils ne se tournent vers la concurrence.', 'Prospects', route('prospects.index'));
            }
            $expirent = Reservation::where('statut', 'active')->whereBetween('date_expiration', [today(), today()->addDays(3)])->count();
            if ($expirent) {
                $ajouter('info', "$expirent réservation(s) expirent sous 3 jours", 'Transformez-les en contrats ou prolongez-les, sinon les biens seront libérés automatiquement.', 'Réservations', route('reservations.index'));
            }
            $nonPublies = Bien::disponibles()->where('publie', false)->where(fn ($q) => $q->where('a_vendre', true)->orWhere('a_louer', true))->count();
            if ($nonPublies && $this->actif('vitrine')) {
                $ajouter('info', "$nonPublies bien(s) disponible(s) non publié(s) sur le site", 'Publiez-les avec des photos pour générer des demandes de visite.', 'Biens', route('biens.index', ['statut' => 'disponible']));
            }
        }

        if ($this->actif('syndic')) {
            $appele = (float) AppelLigne::where('statut', '!=', 'annule')->whereHas('appel', fn ($a) => $a->whereDate('date_echeance', '<', today()))->sum('montant');
            $paye = (float) AppelLigne::where('statut', '!=', 'annule')->whereHas('appel', fn ($a) => $a->whereDate('date_echeance', '<', today()))->sum('montant_paye');
            if ($appele > 0 && $paye / $appele < 0.8) {
                $ajouter('warning', 'Charges de copropriété recouvrées à ' . round($paye / $appele * 100) . ' %', 'Les fonds manquants risquent de bloquer l\'entretien : relancez les copropriétaires débiteurs.',
                    'Appels de fonds', route('appelsfonds.index'));
            }
        }

        $mesures = $this->mesures(today()->subMonths(3), today());
        $ratio = (float) $this->parametres->get('analyse.ratio_masse_salariale');
        if ($mesures['encaissements'] > 0 && $mesures['masse_salariale'] / $mesures['encaissements'] * 100 > $ratio) {
            $ajouter('warning', 'Masse salariale à ' . round($mesures['masse_salariale'] / $mesures['encaissements'] * 100) . ' % des encaissements (seuil ' . $ratio . ' %)',
                'Sur les 3 derniers mois. Améliorez le recouvrement ou réexaminez les effectifs et commissions.');
        }
        if (($m = $mesures['taux_recouvrement']) !== null && $m < (float) $this->parametres->get('analyse.objectif_recouvrement')) {
            $ajouter('warning', 'Recouvrement des échéances à ' . $m . ' % sur 3 mois', 'Objectif : ' . $this->parametres->get('analyse.objectif_recouvrement') . ' %. Activez les relances automatiques et suivez les promesses de paiement.',
                'Niveaux de relance', $this->actif('recouvrement') ? route('reglesrelance.index') : null);
        }

        $pieces = TiersPiece::whereDate('date_expiration', '<', today())->count();
        if ($pieces) {
            $ajouter('info', "$pieces pièce(s) d'identité expirée(s)", 'Mettez à jour les dossiers clients (obligation de vigilance avant toute signature).', 'Tiers', route('tiers.index'));
        }

        if (empty($r)) {
            $ajouter('success', 'Aucun point d\'attention', 'Les indicateurs sont dans les objectifs fixés (Paramètres métier › Analyse).');
        }

        return $r;
    }

    /**
     * Simulation sur 12 mois : effet du recouvrement des arriérés, d'une hausse des loyers,
     * d'un objectif d'occupation et d'une remise sur le stock invendu.
     *
     * @param  array{recouvrement: float, hausse_loyers: float, occupation: float, remise_stock: float, ventes_stock: float}  $h
     */
    public function simuler(array $h): array
    {
        $arrieres = (float) Recouvrement::whereNotIn('statut', ['solde', 'abandonne'])->sum('montant_du');
        $loyersMensuels = (float) Bail::where('statut', 'actif')->sum('loyer_mensuel');
        $aLouer = Bien::where('a_louer', true)->count();
        $loues = Bien::where('a_louer', true)->where('statut', 'loue')->count();
        $loyerMoyen = (float) (Bien::where('a_louer', true)->avg('loyer_mensuel') ?? 0);
        $stock = (float) Bien::disponibles()->where('a_vendre', true)->sum('prix_vente');
        $occupationActuelle = $aLouer ? $loues / $aLouer * 100 : 0;

        $lignes = [
            'Recouvrement des arriérés' => $arrieres * $h['recouvrement'] / 100,
            'Loyers des baux en cours (12 mois)' => $loyersMensuels * 12 * (1 + $h['hausse_loyers'] / 100),
            'Biens vacants remis en location' => max(0, ($h['occupation'] - $occupationActuelle) / 100 * $aLouer) * $loyerMoyen * (1 + $h['hausse_loyers'] / 100) * 6, // 6 mois en moyenne
            'Ventes du stock disponible' => $stock * (1 - $h['remise_stock'] / 100) * $h['ventes_stock'] / 100,
        ];

        return [
            'lignes' => $lignes,
            'total' => array_sum($lignes),
            'base' => ['arrieres' => $arrieres, 'loyers' => $loyersMensuels, 'occupation' => round($occupationActuelle, 1), 'stock' => $stock, 'vacants' => $aLouer - $loues],
        ];
    }
}
