<?php

namespace App\Http\Controllers\Compta;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Compta\Compte;
use App\Models\Compta\Ecriture;
use App\Models\Compta\Journal;
use App\Services\Compta\ComptaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EcritureController extends Controller
{
    public function __construct(protected ComptaService $compta)
    {
    }

    public function index(Request $request)
    {
        $du = $request->filled('du') ? Carbon::parse($request->du) : today()->startOfMonth();
        $au = $request->filled('au') ? Carbon::parse($request->au) : today();

        $ecritures = Ecriture::with(['journal', 'lignes.compte', 'lignes.tiers'])
            ->whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])
            ->when($request->filled('journal_id'), fn ($q) => $q->where('journal_id', $request->journal_id))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('numero', 'ilike', '%' . $request->q . '%')
                ->orWhere('libelle', 'ilike', '%' . $request->q . '%')->orWhere('piece', 'ilike', '%' . $request->q . '%')))
            ->orderByDesc('date_ecriture')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('compta.ecritures', [
            'ecritures' => $ecritures,
            'journaux' => Journal::orderBy('code')->get()->mapWithKeys(fn ($j) => [$j->id => $j->code . ' — ' . $j->libelle]),
            'du' => $du, 'au' => $au,
            'comptes' => Compte::where('actif', true)->orderBy('numero')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'journal' => ['required', Rule::exists('cpt_journaux', 'code')->where('agence_id', agence()->id)],
            'date_ecriture' => ['required', 'date'],
            'libelle' => ['required', 'string', 'max:255'],
            'piece' => ['nullable', 'string', 'max:60'],
            'lignes' => ['required', 'array', 'min:2'],
            'lignes.*.compte' => ['nullable', Rule::exists('cpt_comptes', 'numero')->where('agence_id', agence()->id)],
            'lignes.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lignes.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lignes.*.libelle' => ['nullable', 'string', 'max:255'],
        ]);

        $lignes = collect($donnees['lignes'])->filter(fn ($l) => !empty($l['compte']) && ((float) ($l['debit'] ?? 0) || (float) ($l['credit'] ?? 0)))
            ->map(fn ($l) => ['compte' => $l['compte'], 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0, 'libelle' => $l['libelle'] ?: $donnees['libelle']])->values()->all();

        $ecriture = $this->compta->passer($donnees['journal'], $donnees['date_ecriture'], $donnees['libelle'], $lignes, null, $donnees['piece'] ?? null, 'manuelle', operation: 'MANUELLE');
        Audit::logSave(['action' => 'ECRITURE MANUELLE', 'code_piece' => $ecriture->numero, 'menu' => 'COMPTABILITE', 'etat' => 'Succès', 'objet' => 'COMPTABILITE']);

        return back()->with('success', 'Écriture ' . $ecriture->numero . ' enregistrée.');
    }

    public function contrepasser(Request $request, Ecriture $ecriture)
    {
        abort_if(Ecriture::where('ecriture_origine_id', $ecriture->id)->exists() || $ecriture->nature === 'contrepassation', 422, 'Écriture déjà contre-passée.');
        $motif = $request->validate(['motif' => ['required', 'string', 'max:255']])['motif'];
        $inverse = $this->compta->contrepasser($ecriture, today(), $motif);

        return back()->with('success', 'Contre-passation ' . $inverse->numero . ' enregistrée.');
    }

    public function generer(Request $request)
    {
        $donnees = $request->validate(['du' => ['required', 'date'], 'au' => ['required', 'date', 'after_or_equal:du']]);
        $resultat = $this->compta->generer($donnees['du'], $donnees['au']);
        $total = array_sum($resultat) - $resultat['erreurs'];

        return back()->with($resultat['erreurs'] ? 'warning' : 'success', $total . ' écriture(s) générée(s) depuis les opérations'
            . ($resultat['erreurs'] ? ' — ' . $resultat['erreurs'] . ' opération(s) en erreur (exercice clôturé ou règle d\'imputation manquante).' : '.'));
    }
}
