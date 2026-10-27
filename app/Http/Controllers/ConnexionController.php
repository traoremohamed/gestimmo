<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Helpers\Menu;
use App\Models\Menus;
use App\Models\Role;
use App\Models\Sousmenus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConnexionController extends Controller
{
    public function login(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'username' => 'required',
                'password' => 'required',
            ], [
                'username.required' => 'Veuillez saisir votre identifiant.',
                'password.required' => 'Veuillez saisir le mot de passe.',
            ]);

            $username = preg_replace('/\s+/', '', $request->input('username'));
            $champ = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'login_users';
            $compte = User::where($champ, $username)->first();

            // protection contre les tentatives répétées
            if ($compte?->verrouille_jusqu_a?->isFuture()) {
                Audit::logSave(['action' => 'CONNEXION', 'code_piece' => $username, 'menu' => 'CONNEXION', 'etat' => 'Echec', 'motif' => 'Compte verrouillé']);

                return redirect()->route('connexion')->withInput($request->only('username'))
                    ->with('error', 'Compte temporairement verrouillé après plusieurs échecs. Réessayez après ' . $compte->verrouille_jusqu_a->format('H:i') . '.');
            }

            $credentials = [$champ => $username, 'password' => $request->input('password'), 'flag_actif_users' => true];

            if (!Auth::attempt($credentials, $request->boolean('remember'))) {
                if ($compte) {
                    $tentatives = $compte->tentatives_echouees + 1;
                    $max = (int) config('rbac.securite.tentatives_max', 5);
                    $compte->forceFill(['tentatives_echouees' => $tentatives,
                        'verrouille_jusqu_a' => $tentatives >= $max ? now()->addMinutes((int) config('rbac.securite.verrouillage_minutes', 15)) : null])->saveQuietly();
                }
                Audit::logSave(['action' => 'CONNEXION', 'code_piece' => $username, 'menu' => 'CONNEXION', 'etat' => 'Echec']);

                return redirect()->route('connexion')->withInput($request->only('username'))
                    ->with('error', 'Identifiant ou mot de passe incorrect');
            }

            if (!Auth::user()->accesAutorise()) {
                Audit::logSave(['action' => 'CONNEXION', 'code_piece' => $username, 'menu' => 'CONNEXION', 'etat' => 'Echec', 'motif' => 'Accès ou abonnement inactif']);
                Auth::logout();

                return redirect()->route('connexion')->with('error', 'Votre accès n\'est pas actif (compte, période d\'accès ou abonnement de l\'agence). Contactez l\'administrateur.');
            }

            $request->session()->regenerate();
            Auth::user()->forceFill(['tentatives_echouees' => 0, 'verrouille_jusqu_a' => null, 'derniere_connexion_at' => now(), 'derniere_ip' => $request->ip()])->saveQuietly();
            Audit::logSave(['action' => 'CONNEXION', 'menu' => 'CONNEXION', 'etat' => 'Succès']);

            if (!Auth::user()->flag_mdp) {
                return redirect()->route('modifier.mot.passe')
                    ->with('warning', 'Info : veuillez modifier votre mot de passe à la première connexion');
            }

            $logo = Menu::get_logo();

            return redirect()->intended(route('dashboard'))->with('success',
                'Bonjour ' . Auth::user()->name . ' ' . Auth::user()->prenom_users . ', bienvenue sur le portail ' . (@$logo->mot_cle ?: config('rbac.app_name')));
        }

        return view('connexion.login');
    }

    public function logout(Request $request)
    {
        Audit::logSave(['action' => 'DECONNEXION', 'menu' => 'CONNEXION', 'etat' => 'Succès']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion');
    }

    public function dashboard()
    {
        $idutil = Auth::id();
        $naroles = Menu::get_menu_profil($idutil);
        $date = now()->format('d/m/Y');

        $stats = [
            'Utilisateurs' => User::count(),
            'Profils' => Role::count(),
            'Modules' => Menus::count(),
            'Sous-modules' => Sousmenus::count(),
        ];

        return view('dashboard.dashboard', compact('naroles', 'date', 'stats'));
    }
}
