<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Helpers\Crypt;
use App\Models\HistoriqueMotDePasse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    /**
     * Changement du mot de passe (obligatoire à la première connexion).
     */
    public function updatepassword(Request $request)
    {
        $user = Auth::user();

        if ($request->isMethod('post')) {
            $request->validate([
                'cpwd' => 'required',
                'npwd' => 'required|same:vpwd',
                'vpwd' => 'required',
            ], [
                'cpwd.required' => 'Veuillez saisir l\'ancien mot de passe.',
                'npwd.required' => 'Veuillez saisir le nouveau mot de passe.',
                'npwd.same' => 'Le nouveau mot de passe et la confirmation ne correspondent pas.',
                'vpwd.required' => 'Veuillez ressaisir le nouveau mot de passe.',
            ]);

            if (!Hash::check($request->cpwd, $user->password)) {
                return back()->with('error', 'L\'ancien mot de passe est incorrect.');
            }

            $verif = Crypt::VerifierMotDePasse($request->npwd);
            if ($verif !== 'mot de passe correcte') {
                return back()->with('error', $verif);
            }

            $dejaUtilise = Hash::check($request->npwd, $user->password)
                || $user->historiqueMotDePasse()->latest('id_historique')->limit(5)->get()
                    ->contains(fn ($h) => Hash::check($request->npwd, $h->ancien_mot_de_passe_hash));

            if ($dejaUtilise) {
                return back()->with('error', 'Ce mot de passe a déjà été utilisé, veuillez en choisir un autre.');
            }

            HistoriqueMotDePasse::create([
                'id_utilisateur' => $user->id,
                'ancien_mot_de_passe_hash' => $user->password,
                'date_changement' => now(),
            ]);

            $user->forceFill([
                'password' => Hash::make($request->npwd),
                'flag_mdp' => true,
                'flag_mod_pswd_first' => true,
            ])->save();

            Audit::logSave(['action' => 'MODIFIER MOT DE PASSE', 'code_piece' => $user->id, 'menu' => 'PROFIL', 'etat' => 'Succès']);

            return redirect()->route('dashboard')->with('success', 'Mot de passe modifié avec succès.');
        }

        return view('profil.updatepassword');
    }
}
