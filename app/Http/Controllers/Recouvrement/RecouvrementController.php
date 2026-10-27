<?php

namespace App\Http\Controllers\Recouvrement;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Recouvrement;
use App\Models\RegleRelance;
use App\Models\Relance;
use App\Models\User;
use App\Services\Recouvrement\RecouvrementService;
use App\Support\Pdf;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Recouvrement : portefeuille des débiteurs, relances, promesses de paiement, contentieux.
 * Un gestionnaire ne voit que ses dossiers (et ceux non attribués), sauf permission recouvrements-tous.
 */
class RecouvrementController extends Controller
{
    public function __construct(protected RecouvrementService $service)
    {
    }

    public function index(Request $request)
    {
        $base = $this->restreindre(Recouvrement::query());
        $ouverts = (clone $base)->whereNotIn('statut', ['solde', 'abandonne']);

        $dossiers = (clone $base)->with(['tiers', 'gestionnaire'])->withCount('relances')
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut), fn ($q) => $q->whereNotIn('statut', ['solde', 'abandonne']))
            ->when($request->filled('activite'), fn ($q) => $q->where('activite', $request->activite))
            ->when($request->query('action') === 'jour', fn ($q) => $q->whereDate('prochaine_action', '<=', today()))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('tiers', fn ($t) => $t->recherche($request->q)))
            ->orderByDesc('montant_du');

        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            $tous = $dossiers->limit(20000)->get();

            return Export::telecharger($request->query('format'), 'Dossiers de recouvrement', ['Débiteur', 'Téléphone', 'Activité', 'Montant dû', 'Plus ancienne échéance',
                'Retard (j)', 'Niveau', 'Relances', 'Statut', 'Gestionnaire', 'Prochaine action'],
                $tous->map(fn ($d) => [$d->tiers?->nom_complet, $d->tiers?->telephone, Recouvrement::ACTIVITES[$d->activite] ?? $d->activite, (float) $d->montant_du,
                    $d->plus_ancienne_echeance?->format('d/m/Y'), $d->joursRetard(), $d->niveau, $d->relances_count, Recouvrement::STATUTS[$d->statut][0] ?? $d->statut,
                    $d->gestionnaire?->name, $d->prochaine_action?->format('d/m/Y')])->all(),
                ['Total', '', '', (float) $tous->sum('montant_du'), '', '', '', '', '', '', '']);
        }
        $dossiers = $dossiers->paginate(30)->withQueryString();

        $tranches = ['0-30 j' => [0, 30], '31-60 j' => [31, 60], '61-90 j' => [61, 90], '> 90 j' => [91, 100000]];
        $parTranche = collect($tranches)->map(fn ($t) => (clone $ouverts)->whereBetween('plus_ancienne_echeance', [today()->subDays($t[1]), today()->subDays($t[0])])->sum('montant_du'));

        return view('recouvrement.index', [
            'dossiers' => $dossiers,
            'total' => (clone $ouverts)->sum('montant_du'),
            'nombre' => (clone $ouverts)->count(),
            'parActivite' => (clone $ouverts)->selectRaw('activite, sum(montant_du) as total, count(*) as nombre')->groupBy('activite')->get()->keyBy('activite'),
            'parTranche' => $parTranche,
            'contentieux' => (clone $base)->where('statut', 'contentieux')->sum('montant_du'),
        ]);
    }

    public function show(Recouvrement $recouvrement)
    {
        $this->autoriser($recouvrement);
        $recouvrement->load(['tiers', 'gestionnaire', 'relances.auteur', 'promesses']);

        return view('recouvrement.show', [
            'dossier' => $recouvrement,
            'dettes' => $this->service->details($recouvrement),
            'regles' => RegleRelance::where('actif', true)->orderBy('niveau')->get(),
            'gestionnaires' => User::where('agence_id', agence()->id)->where('flag_actif_users', true)->orderBy('name')->get()->pluck('nom_complet', 'id'),
            'apercu' => $this->service->remplir(RegleRelance::where('actif', true)->where('niveau', '>', $recouvrement->niveau)->orderBy('niveau')->value('message')
                ?? 'Bonjour {nom}, un montant de {montant} reste dû. {agence}', $recouvrement),
        ]);
    }

    public function analyser()
    {
        $resultat = $this->service->analyser();

        return back()->with('success', $resultat['ouverts'] . ' dossier(s) ouvert(s) mis à jour avec les impayés réels.');
    }

    public function relancerTout()
    {
        $this->service->analyser();
        $nombre = $this->service->relancesAutomatiques();
        Audit::logSave(['action' => 'RELANCES AUTOMATIQUES', 'code_piece' => $nombre, 'menu' => 'RECOUVREMENT', 'etat' => 'Succès', 'objet' => 'RECOUVREMENT']);

        return back()->with('success', $nombre . ' relance(s) envoyée(s) ou préparée(s) selon les niveaux paramétrés.');
    }

    public function relancer(Request $request, Recouvrement $recouvrement)
    {
        $this->autoriser($recouvrement);
        $donnees = $request->validate([
            'regle_id' => ['nullable', Rule::exists('regles_relance', 'id')->where('agence_id', agence()->id)],
            'canal' => ['required', Rule::in(array_keys(RegleRelance::CANAUX))],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $relance = $this->service->relancer($recouvrement, isset($donnees['regle_id']) ? RegleRelance::find($donnees['regle_id']) : null,
            $donnees['canal'], $donnees['message'] ?? null, $request->user());
        Audit::logSave(['action' => 'RELANCER', 'code_piece' => $recouvrement->id, 'menu' => 'RECOUVREMENT', 'etat' => 'Succès', 'objet' => 'RECOUVREMENT']);

        $message = match ($relance->statut) {
            'envoyee' => 'Relance envoyée par ' . RegleRelance::CANAUX[$relance->canal] . '.',
            'a_imprimer' => 'Lettre de relance prête : imprimez-la depuis l\'historique.',
            'echec' => 'Relance enregistrée mais non envoyée : coordonnées manquantes pour ce canal.',
            default => 'Action de relance enregistrée.',
        };

        return back()->with($relance->statut === 'echec' ? 'warning' : 'success', $message);
    }

    public function promettre(Request $request, Recouvrement $recouvrement)
    {
        $this->autoriser($recouvrement);
        $donnees = $request->validate([
            'date_promesse' => ['required', 'date', 'after_or_equal:today'],
            'montant' => ['required', 'numeric', 'gt:0'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->promettre($recouvrement, Carbon::parse($donnees['date_promesse']), $donnees['montant'], $donnees['commentaire'] ?? null);

        return back()->with('success', 'Promesse de paiement enregistrée : le dossier sera vérifié le ' . Carbon::parse($donnees['date_promesse'])->format('d/m/Y') . '.');
    }

    public function traiter(Request $request, Recouvrement $recouvrement)
    {
        $this->autoriser($recouvrement);
        $donnees = $request->validate([
            'statut' => ['required', Rule::in(['amiable', 'contentieux', 'protocole', 'abandonne'])],
            'gestionnaire_id' => ['nullable', Rule::exists('users', 'id')->where('agence_id', agence()->id)],
            'prochaine_action' => ['nullable', 'date'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->changerStatut($recouvrement, $donnees['statut'], $donnees['commentaire'] ?? null);
        $recouvrement->update(['gestionnaire_id' => $donnees['gestionnaire_id'] ?? null, 'prochaine_action' => $donnees['prochaine_action'] ?? null]);

        return back()->with('success', 'Dossier mis à jour.');
    }

    public function lettre(Recouvrement $recouvrement, Relance $relance)
    {
        $this->autoriser($recouvrement);
        abort_unless($relance->recouvrement_id === $recouvrement->id, 404);
        $recouvrement->load('tiers');

        return Pdf::telecharger('pdf.lettre_relance', ['dossier' => $recouvrement, 'relance' => $relance], 'relance-' . $recouvrement->tiers->nom . '-' . $relance->id,
            objet: $relance, type: 'LETTRE_RELANCE', etat: (string) $relance->id);
    }

    protected function restreindre(Builder $query): Builder
    {
        return auth()->user()->can('recouvrements-tous') ? $query
            : $query->where(fn ($q) => $q->where('gestionnaire_id', auth()->id())->orWhereNull('gestionnaire_id'));
    }

    protected function autoriser(Recouvrement $dossier): void
    {
        abort_unless(auth()->user()->can('recouvrements-tous') || in_array($dossier->gestionnaire_id, [null, auth()->id()], true), 404);
    }
}
