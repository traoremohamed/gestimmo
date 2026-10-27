<?php

namespace App\Http\Controllers\Location;

use App\Enums\RoleTiers;
use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Referentiel;
use App\Models\Reversement;
use App\Models\Tiers;
use App\Services\Location\ReversementService;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReversementController extends Controller
{
    public function __construct(protected ReversementService $service)
    {
    }

    public function index(Request $request)
    {
        $reversements = Reversement::with('proprietaire')
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest()->paginate(25)->withQueryString();

        return view('location.reversements', compact('reversements'));
    }

    public function create(Request $request)
    {
        $proprietaires = Tiers::avecRole(RoleTiers::Proprietaire)->orderBy('nom')->get();
        $fin = $request->filled('fin') ? Carbon::parse($request->fin) : today()->subMonthNoOverflow()->endOfMonth();
        $proprietaire = $request->filled('proprietaire_id') ? $proprietaires->firstWhere('id', (int) $request->proprietaire_id) : null;

        return view('location.reversement_form', [
            'proprietaires' => $proprietaires->pluck('nom_complet', 'id'),
            'proprietaire' => $proprietaire,
            'debut' => $request->filled('debut') ? Carbon::parse($request->debut) : $fin->copy()->startOfMonth(),
            'fin' => $fin,
            'loyers' => $proprietaire ? $this->service->loyersAReverser($proprietaire, $fin) : collect(),
            'depenses' => $proprietaire ? $this->service->depensesADeduire($proprietaire, $fin) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'proprietaire_id' => ['required', Rule::exists('tiers', 'id')->where('agence_id', agence()->id)],
            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:debut'],
        ]);

        $reversement = $this->service->preparer(Tiers::findOrFail($donnees['proprietaire_id']), Carbon::parse($donnees['debut']), Carbon::parse($donnees['fin']));

        return redirect()->route('reversements.show', $reversement)->with('success', 'Reversement préparé : vérifiez-le puis validez.');
    }

    public function show(Reversement $reversement)
    {
        $reversement->load(['proprietaire', 'lignes.loyer.bail.bien', 'depenses']);

        return view('location.reversement', ['reversement' => $reversement, 'modes' => Referentiel::options('mode_paiement')]);
    }

    public function valider(Reversement $reversement)
    {
        $this->service->valider($reversement);
        Audit::logSave(['action' => 'VALIDER', 'code_piece' => $reversement->numero, 'menu' => 'REVERSEMENTS', 'etat' => 'Succès', 'objet' => 'GESTION LOCATIVE']);

        return back()->with('success', 'Reversement validé.');
    }

    public function payer(Request $request, Reversement $reversement)
    {
        $donnees = $request->validate([
            'mode_paiement' => ['required', Rule::in(Referentiel::options('mode_paiement')->keys())],
            'reference_paiement' => ['nullable', 'string', 'max:255'],
        ]);

        if ($attente = $this->soumettre('REVERSEMENT_PAIEMENT', $reversement, ['mode_paiement' => $donnees['mode_paiement'], 'reference' => $donnees['reference_paiement'] ?? null])) {
            return $attente;
        }
        $this->service->payer($reversement, $donnees['mode_paiement'], $donnees['reference_paiement'] ?? null);
        Audit::logSave(['action' => 'PAYER', 'code_piece' => $reversement->numero, 'menu' => 'REVERSEMENTS', 'etat' => 'Succès', 'objet' => 'GESTION LOCATIVE']);

        return back()->with('success', 'Paiement du reversement enregistré.');
    }

    public function releve(Reversement $reversement)
    {
        $reversement->load(['proprietaire', 'lignes', 'depenses']);

        return Pdf::telecharger('pdf.releve_reversement', ['reversement' => $reversement], 'releve-' . $reversement->numero, objet: $reversement, type: 'RELEVE_REVERSEMENT', etat: $reversement->statut->value);
    }

    public function destroy(Reversement $reversement)
    {
        $this->service->supprimerBrouillon($reversement);

        return redirect()->route('reversements.index')->with('success', 'Brouillon supprimé.');
    }
}
