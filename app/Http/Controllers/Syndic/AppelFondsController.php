<?php

namespace App\Http\Controllers\Syndic;

use App\Enums\StatutAppelFonds;
use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\AppelFonds;
use App\Models\AppelLigne;
use App\Models\Copropriete;
use App\Services\Syndic\SyndicService;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppelFondsController extends Controller
{
    public function __construct(protected SyndicService $service)
    {
    }

    public function index(Request $request)
    {
        $appels = AppelFonds::with('copropriete')->withSum('lignes as total_paye', 'montant_paye')
            ->when($request->filled('copropriete_id'), fn ($q) => $q->where('copropriete_id', $request->copropriete_id))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->orderByDesc('date_appel')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('syndic.appels', ['appels' => $appels, 'coproprietes' => Copropriete::orderBy('nom')->pluck('nom', 'id')]);
    }

    public function create(Request $request)
    {
        $coproprietes = Copropriete::with('cles')->orderBy('nom')->get();

        return view('syndic.appel_form', ['coproprietes' => $coproprietes, 'coproprieteId' => $request->query('copropriete_id')]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'copropriete_id' => ['required', Rule::exists('coproprietes', 'id')->where('agence_id', agence()->id)],
            'type' => ['required', Rule::in(array_keys(AppelFonds::TYPES))],
            'libelle' => ['required', 'string', 'max:255'],
            'cle_repartition_id' => ['nullable', Rule::exists('cles_repartition', 'id')->where('copropriete_id', $request->copropriete_id)],
            'date_appel' => ['required', 'date'],
            'date_echeance' => ['required', 'date', 'after_or_equal:date_appel'],
            'montant_total' => ['required', 'numeric', 'gt:0'],
        ]);

        $appel = AppelFonds::create($donnees + ['statut' => StatutAppelFonds::Brouillon, 'created_by' => $request->user()->id]);

        return redirect()->route('appelsfonds.show', $appel)->with('success', 'Appel de fonds créé en brouillon.');
    }

    public function show(AppelFonds $appel)
    {
        $appel->load(['copropriete', 'cle', 'lignes.lot', 'lignes.coproprietaire']);

        return view('syndic.appel', compact('appel'));
    }

    public function emettre(AppelFonds $appel)
    {
        if ($attente = $this->soumettre('APPEL_FONDS_EMISSION', $appel, filtre: $appel->type)) {
            return $attente;
        }
        $this->service->emettre($appel);
        Audit::logSave(['action' => 'EMETTRE', 'code_piece' => $appel->numero, 'menu' => 'APPELS DE FONDS', 'etat' => 'Succès', 'objet' => 'SYNDIC']);

        return redirect()->route('appelsfonds.show', $appel)->with('success', 'Appel ' . $appel->numero . ' émis et réparti entre les lots.');
    }

    public function annuler(AppelFonds $appel)
    {
        $this->service->annulerAppel($appel);

        return back()->with('success', 'Appel de fonds annulé.');
    }

    public function avis(AppelFonds $appel, AppelLigne $ligne)
    {
        abort_unless($ligne->appel_fonds_id === $appel->id, 404);
        $ligne->load(['lot', 'coproprietaire']);
        $appel->load('copropriete');

        return Pdf::telecharger('pdf.avis_appel', ['appel' => $appel, 'ligne' => $ligne], 'avis-' . $appel->numero . '-' . $ligne->lot->numero,
            objet: $ligne, type: 'AVIS_APPEL', etat: md5($ligne->montant . '|' . $appel->statut->value));
    }
}
