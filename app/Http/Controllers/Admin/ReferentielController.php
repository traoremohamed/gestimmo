<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referentiel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Listes de valeurs paramétrables de l'agence (types de bien, modes de paiement…).
 * Les valeurs ne sont jamais supprimées (elles sont référencées par leur code) : on les désactive.
 */
class ReferentielController extends Controller
{
    public function index(Request $request)
    {
        $categories = collect(config('immobilier.referentiels'))->map(fn ($c) => $c[0]);
        $categorie = $request->query('categorie', $categories->keys()->first());
        abort_unless($categories->has($categorie), 404);

        return view('admin.referentiels', [
            'categories' => $categories,
            'categorie' => $categorie,
            'valeurs' => Referentiel::categorie($categorie)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $categories = array_keys(config('immobilier.referentiels'));
        $donnees = $request->validate([
            'categorie' => ['required', Rule::in($categories)],
            'libelle' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'alpha_dash', 'max:50'],
            'ordre' => ['nullable', 'integer'],
        ]);

        $donnees['code'] = strtoupper($donnees['code'] ?: Str::slug($donnees['libelle'], '_'));

        $existe = Referentiel::where('categorie', $donnees['categorie'])->where('code', $donnees['code'])->exists();
        if ($existe) {
            return back()->withInput()->with('error', 'Ce code existe déjà dans la liste.');
        }

        Referentiel::create($donnees + ['actif' => true, 'ordre' => $donnees['ordre'] ?? 99]);

        return redirect()->route('referentiels', ['categorie' => $donnees['categorie']])->with('success', 'Valeur ajoutée.');
    }

    public function update(Request $request, Referentiel $referentiel)
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer'],
            'actif' => ['boolean'],
        ]);

        $referentiel->update(['libelle' => $donnees['libelle'], 'ordre' => $donnees['ordre'] ?? $referentiel->ordre, 'actif' => $request->boolean('actif')]);

        return redirect()->route('referentiels', ['categorie' => $referentiel->categorie])->with('success', 'Valeur modifiée.');
    }
}
