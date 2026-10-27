<?php

namespace App\Http\Controllers\Validation;

use App\Http\Controllers\Controller;
use App\Models\Validation\Demande;
use App\Services\Validation\Catalogue;
use App\Services\Validation\ValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Demandes de validation : celles que j'ai à décider, celles que j'ai soumises, toutes (pilotage).
 */
class ValidationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $onglet = $request->input('onglet', 'a_decider');

        $liste = match ($onglet) {
            'mes_demandes' => Demande::with(['circuit', 'demandeur'])->where('demandeur_id', $user->id)->latest('demande_at')->paginate(30)->withQueryString(),
            'toutes' => $user->can('validations-toutes')
                ? Demande::with(['circuit', 'demandeur'])->when($request->input('statut'), fn ($q, $s) => $q->where('statut', $s))
                    ->when($request->input('action'), fn ($q, $a) => $q->where('action', $a))->latest('demande_at')->paginate(30)->withQueryString()
                : abort(403),
            default => null,
        };

        return view('validations.index', ['onglet' => $onglet, 'aDecider' => ValidationService::aDecider($user), 'liste' => $liste, 'actions' => Catalogue::options()]);
    }

    public function show(Demande $demande)
    {
        $user = Auth::user();
        $demande->load(['circuit.etapes.role', 'circuit.etapes.validateur', 'demandeur', 'decisions.auteur']);
        $peutDecider = ValidationService::peutDecider($user, $demande);
        abort_unless($peutDecider || (int) $demande->demandeur_id === (int) $user->id || $user->can('validations-toutes')
            || $demande->decisions->contains('decide_par', $user->id), 403);

        return view('validations.show', [
            'demande' => $demande, 'peutDecider' => $peutDecider,
            'etapes' => ValidationService::etapesApplicables($demande->circuit, $demande->montant !== null ? (float) $demande->montant : null),
        ]);
    }

    public function decider(Request $request, Demande $demande)
    {
        $donnees = $request->validate(['decision' => 'required|in:APPROUVE,REJETE', 'commentaire' => 'nullable|string|max:2000']);
        try {
            $demande = ValidationService::decider($demande, $donnees['decision'], $donnees['commentaire'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('validations.show', $demande)->with($demande->statut === 'ECHEC' ? 'error' : 'success', match ($demande->statut) {
            'EXECUTEE' => 'Approuvée : l\'action a été exécutée.',
            'ECHEC' => 'Approuvée, mais l\'exécution a échoué : ' . $demande->erreur,
            'REJETEE' => 'Demande rejetée.',
            default => 'Étape approuvée : la demande passe à l\'étape suivante.',
        });
    }

    public function annuler(Request $request, Demande $demande)
    {
        $motif = $request->validate(['motif' => 'required|string|min:3|max:500'])['motif'];
        try {
            ValidationService::annuler($demande, $motif);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Demande retirée.');
    }

    public function executer(Demande $demande)
    {
        abort_unless(Auth::user()->can('validations-toutes'), 403);
        try {
            $demande = ValidationService::executer($demande);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($demande->statut === 'EXECUTEE' ? 'success' : 'error', $demande->statut === 'EXECUTEE' ? 'Action exécutée.' : 'Nouvel échec : ' . $demande->erreur);
    }
}
