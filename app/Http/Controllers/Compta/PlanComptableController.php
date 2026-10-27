<?php

namespace App\Http\Controllers\Compta;

use App\Http\Controllers\Controller;
use App\Models\Compta\Compte;
use App\Models\Compta\Journal;
use App\Models\Compta\Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plan comptable de l'agence et règles d'imputation des écritures automatiques.
 */
class PlanComptableController extends Controller
{
    public function index(Request $request)
    {
        return view('compta.plan', [
            'comptes' => Compte::withCount('lignes')->orderBy('numero')->get(),
            'schemas' => Schema::orderBy('operation')->orderBy('cle')->get()->groupBy('operation'),
            'journaux' => Journal::orderBy('code')->get(),
        ]);
    }

    public function storeCompte(Request $request)
    {
        $donnees = $request->validate([
            'numero' => ['required', 'regex:/^[1-9][0-9]{1,9}$/', Rule::unique('cpt_comptes', 'numero')->where('agence_id', agence()->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(Compte::TYPES))],
            'lettrable' => ['boolean'],
        ], ['numero.regex' => 'Un numéro de compte commence par la classe (1 à 9) et ne contient que des chiffres.']);

        Compte::create($donnees + ['classe' => (int) $donnees['numero'][0], 'lettrable' => $request->boolean('lettrable'), 'actif' => true]);

        return back()->with('success', 'Compte ' . $donnees['numero'] . ' créé.');
    }

    public function updateCompte(Request $request, Compte $compte)
    {
        $donnees = $request->validate(['libelle' => ['required', 'string', 'max:255'], 'actif' => ['boolean']]);
        $compte->update(['libelle' => $donnees['libelle'], 'actif' => $request->boolean('actif')]);

        return back()->with('success', 'Compte modifié.');
    }

    public function updateSchemas(Request $request)
    {
        $donnees = $request->validate([
            'schemas' => ['required', 'array'],
            'schemas.*.compte' => ['required', Rule::exists('cpt_comptes', 'numero')->where('agence_id', agence()->id)],
            'schemas.*.journal' => ['nullable', Rule::exists('cpt_journaux', 'code')->where('agence_id', agence()->id)],
        ], ['schemas.*.compte.exists' => 'Un compte saisi n\'existe pas dans le plan comptable.']);

        foreach ($donnees['schemas'] as $id => $valeurs) {
            Schema::whereKey($id)->update(['compte' => $valeurs['compte'], 'journal' => $valeurs['journal'] ?? null]);
        }

        return back()->with('success', 'Règles d\'imputation enregistrées : elles s\'appliquent aux prochaines opérations.');
    }
}
