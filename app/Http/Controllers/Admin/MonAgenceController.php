<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Pays;
use App\Services\AgenceService;
use App\Support\AgenceCourante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Identité de l'agence (logo, couleur, coordonnées). Les modules ont leur propre écran (ModuleController),
 * la publication du site vitrine se fait dans « Site web ».
 */
class MonAgenceController extends Controller
{
    public function __construct(protected AgenceCourante $agenceCourante, protected AgenceService $service)
    {
    }

    public function edit()
    {
        return view('admin.monagence', [
            'agence' => $this->agenceCourante->agence(),
            'pays' => Pays::orderBy('libelle')->pluck('libelle', 'id'),
        ]);
    }

    public function update(Request $request)
    {
        $agence = $this->agenceCourante->agence();

        $donnees = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'sigle' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'telephone2' => ['nullable', 'string', 'max:50'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:255'],
            'pays_id' => ['nullable', 'exists:pays,id'],
            'rccm' => ['nullable', 'string', 'max:80'],
            'compte_contribuable' => ['nullable', 'string', 'max:80'],
            'couleur' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'devise' => ['required', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($agence->logo) {
                Storage::disk('public')->delete($agence->logo);
            }
            $donnees['logo'] = $request->file('logo')->store('agences/' . $agence->id . '/logo', 'public');
        } else {
            unset($donnees['logo']);
        }

        $agence->update($donnees);

        Audit::logSave(['action' => 'MODIFIER', 'code_piece' => $agence->id, 'menu' => 'MON AGENCE', 'etat' => 'Succès', 'objet' => 'PARAMETRAGE']);

        return redirect()->route('monagence')->with('success', 'Paramètres de l\'agence enregistrés.');
    }
}
