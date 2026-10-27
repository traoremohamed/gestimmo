<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Helpers\Crypt;
use App\Models\Logo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Paramètres système (table logo) : logo, couleur du menu, images, contacts...
 * Règles : un seul paramètre actif par type, sauf les types "multiples"
 * (config rbac.parametres.multiples).
 */
class ParametreController extends Controller
{
    public function parametresysteme()
    {
        $logos = Logo::orderBy('valeur')->orderBy('id_logo')->get();

        return view('logos.index', compact('logos'));
    }

    public function creerparametresysteme(Request $request)
    {
        $types = config('rbac.parametres.types');

        if ($request->isMethod('post')) {
            $request->validate($this->regles());

            $logo = new Logo([
                'titre_logo' => $request->input('titre_logo'),
                'valeur' => $request->input('valeur'),
                'mot_cle' => $request->input('mot_cle'),
                'id_user' => Auth::id(),
            ]);

            // actif seulement si demandé et si le type le permet
            $logo->flag_logo = $request->boolean('flag_logo') && $this->peutActiver($logo->valeur);

            if ($request->hasFile('logo_logo')) {
                $logo->logo_logo = $this->enregistrerFichier($request);
            }

            $logo->save();

            Audit::logSave(['action' => 'CREER', 'code_piece' => $logo->id_logo, 'menu' => 'PARAMETRES SYSTEMES', 'etat' => 'Succès', 'objet' => 'CONFIGURATION']);

            return redirect()->route('parametresysteme')->with('success', 'Enregistrement effectué');
        }

        return view('logos.creer', compact('types'));
    }

    public function modifierparametresysteme(Request $request, $id)
    {
        $id = Crypt::UrldeCrypt($id);
        $logo = Logo::findOrFail($id);
        $types = config('rbac.parametres.types');

        if ($request->isMethod('post')) {
            $request->validate($this->regles());

            $logo->fill([
                'titre_logo' => $request->input('titre_logo'),
                'valeur' => $request->input('valeur'),
                'mot_cle' => $request->input('mot_cle'),
                'id_user' => Auth::id(),
            ]);

            if ($request->hasFile('logo_logo')) {
                $logo->logo_logo = $this->enregistrerFichier($request);
            }

            $logo->save();

            Audit::logSave(['action' => 'MODIFIER', 'code_piece' => $logo->id_logo, 'menu' => 'PARAMETRES SYSTEMES', 'etat' => 'Succès', 'objet' => 'CONFIGURATION']);

            return redirect()->route('parametresysteme')->with('success', 'Modification effectuée');
        }

        return view('logos.modifier', compact('logo', 'id', 'types'));
    }

    public function activeparametresysteme($id, $id1)
    {
        $logo = Logo::findOrFail(Crypt::UrldeCrypt($id));

        if (!$this->peutActiver($logo->valeur, $logo->id_logo)) {
            return redirect()->route('parametresysteme')->with('error', 'Vous ne pouvez pas activer plus d\'un paramètre de ce type');
        }

        $logo->update(['flag_logo' => true]);
        Audit::logSave(['action' => 'ACTIVER', 'code_piece' => $logo->id_logo, 'menu' => 'PARAMETRES SYSTEMES', 'etat' => 'Succès', 'objet' => 'CONFIGURATION']);

        return redirect()->route('parametresysteme')->with('success', 'Modification effectuée');
    }

    public function desactiveparametresysteme($id, $id1)
    {
        $logo = Logo::findOrFail(Crypt::UrldeCrypt($id));

        $obligatoire = in_array($logo->valeur, config('rbac.parametres.obligatoires', []), true);
        if ($obligatoire && Logo::actifs($logo->valeur)->count() <= 1) {
            return redirect()->route('parametresysteme')->with('error', 'Vous ne pouvez pas désactiver tous les paramètres de ce type');
        }

        $logo->update(['flag_logo' => false]);
        Audit::logSave(['action' => 'DESACTIVER', 'code_piece' => $logo->id_logo, 'menu' => 'PARAMETRES SYSTEMES', 'etat' => 'Succès', 'objet' => 'CONFIGURATION']);

        return redirect()->route('parametresysteme')->with('success', 'Modification effectuée');
    }

    protected function peutActiver(string $type, ?int $saufId = null): bool
    {
        if (in_array($type, config('rbac.parametres.multiples', []), true)) {
            return true;
        }

        return !Logo::actifs($type)->when($saufId, fn ($q) => $q->where('id_logo', '!=', $saufId))->exists();
    }

    protected function regles(): array
    {
        return [
            'valeur' => ['required', Rule::in(array_keys(config('rbac.parametres.types')))],
            'titre_logo' => 'nullable|string|max:255',
            'mot_cle' => 'nullable|string',
            'logo_logo' => 'nullable|file|mimes:png,jpg,jpeg,gif,svg,webp,ico|max:4096',
        ];
    }

    protected function enregistrerFichier(Request $request): string
    {
        $fichier = $request->file('logo_logo');
        $nom = 'logo_logo_' . random_int(111, 99999) . '_' . time() . '.' . $fichier->extension();
        $fichier->move(public_path(config('rbac.parametres.upload_path', 'frontend/logo')), $nom);

        return $nom;
    }
}
