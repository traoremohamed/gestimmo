<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Services\Parametres;
use Illuminate\Http\Request;

/**
 * Paramètres métier de l'agence (taux, délais, numérotation…), déclarés dans config/immobilier.php.
 */
class ParametreMetierController extends Controller
{
    public function __construct(protected Parametres $parametres)
    {
    }

    public function edit()
    {
        return view('admin.parametres', ['groupes' => $this->parametres->parGroupe()]);
    }

    public function update(Request $request)
    {
        $definitions = config('immobilier.parametres');

        $regles = [];
        foreach ($definitions as $cle => $definition) {
            $type = $definition[1];
            $champ = str_replace('.', '__', $cle);
            $regles[$champ] = match ($type) {
                'choix' => ['nullable', 'in:' . implode(',', array_keys($definition[4] ?? []))],
                'entier' => ['nullable', 'integer', 'min:0'],
                'decimal' => ['nullable', 'numeric', 'min:0'],
                'pourcentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'booleen' => ['nullable', 'boolean'],
                'heure' => ['nullable', 'date_format:H:i'],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        $donnees = $request->validate($regles);

        $valeurs = [];
        foreach ($definitions as $cle => [, $type]) {
            $champ = str_replace('.', '__', $cle);
            $valeur = $type === 'booleen' ? $request->boolean($champ) : ($donnees[$champ] ?? null);
            if ((string) $this->parametres->get($cle) !== (string) $valeur) {
                $valeurs[$cle] = $valeur;                               // seules les valeurs modifiées
            }
        }
        $groupes = collect(array_keys($valeurs))->map(fn ($c) => $definitions[$c][3])->unique();
        if ($valeurs && \App\Services\Validation\ValidationService::soumise('PARAMETRES_MODIFICATION', null, null, $groupes->count() === 1 ? $groupes->first() : null)) {
            $demande = \App\Services\Validation\ValidationService::intercepter('PARAMETRES_MODIFICATION', null, ['valeurs' => $valeurs, 'reference' => count($valeurs) . ' paramètre(s)'],
                'Groupes : ' . $groupes->implode(', '), null, $groupes->count() === 1 ? $groupes->first() : null,
                'Modification des paramètres : ' . collect($valeurs)->keys()->map(fn ($c) => $definitions[$c][0])->implode(' ; '));

            return redirect()->route('parametres')->with('info', 'Modification soumise à validation : demande ' . $demande->numero . '.');
        }
        foreach ($valeurs as $cle => $valeur) {
            $this->parametres->definir($cle, $valeur);
        }

        Audit::logSave(['action' => 'MODIFIER', 'menu' => 'PARAMETRES METIER', 'etat' => 'Succès', 'objet' => 'PARAMETRAGE']);

        return redirect()->route('parametres')->with('success', 'Paramètres enregistrés.');
    }
}
