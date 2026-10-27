<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Helpers\Crypt;
use App\Helpers\Email;
use App\Helpers\Envoisms;
use App\Helpers\Menu;
use App\Models\Agence;
use App\Models\Role;
use App\Models\User;
use App\Services\Integration\NotificationService;
use App\Support\AgenceCourante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Utilisateurs (RBAC multi-agences, repris de MARIEATTIEKESAAS) :
 *  - dans une agence : uniquement les utilisateurs de l'agence, créés dans l'agence, avec ses profils ou les profils modèles ;
 *  - super administrateur hors agence : comptes de la plateforme et de toutes les agences.
 * Un identifiant d'une autre agence renvoie 404.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->portee()->with(['roles', 'agence'])
            ->where('flag_demission_users', false)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'ilike', '%' . $request->q . '%')
                ->orWhere('prenom_users', 'ilike', '%' . $request->q . '%')->orWhere('login_users', 'ilike', '%' . $request->q . '%')->orWhere('email', 'ilike', '%' . $request->q . '%')))
            ->orderBy('name')
            ->get();

        Audit::logSave(['action' => 'INDEX', 'menu' => 'LISTE DES UTILISATEURS', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION']);

        return view('users.index', compact('data'));
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['flag_actif_users' => true]), 'roles' => $this->rolesVisibles(), 'roleActuel' => null] + $this->donneesFormulaire());
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $role = $this->profil($request->input('roles'));
        $motDePasse = $request->input('password');

        $user = User::create(array_merge($donnees, [
            'password' => Hash::make($motDePasse),
            'flag_actif_users' => $request->boolean('flag_actif_users', true),
            'flag_mdp' => false,                                         // changement obligatoire à la première connexion
            'agence_id' => $this->agenceCible($request),
            'created_by' => Auth::id(),
        ]));
        $user->assignRole($role);
        $this->synchroniserAgences($request, $user);

        $this->notifierCreation($user, $motDePasse);
        Audit::logSave(['action' => 'CREER', 'code_piece' => $user->id, 'menu' => 'LISTE DES UTILISATEURS', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION',
            'objet_type' => 'users', 'objet_id' => $user->id, 'nouvelles_valeurs' => $donnees + ['profil' => $role->name]]);

        return redirect()->route('users.index')->with('success', 'Utilisateur ' . $user->nom_complet . ' créé. Ses identifiants lui ont été envoyés.');
    }

    public function show($id)
    {
        $user = $this->trouver($id)->load(['roles', 'agence', 'agences']);

        return view('users.show', compact('user'));
    }

    public function edit($id)
    {
        $user = $this->trouver($id)->load('agences');

        return view('users.form', ['user' => $user, 'roles' => $this->rolesVisibles(), 'roleActuel' => $user->roles->first()?->id] + $this->donneesFormulaire());
    }

    public function update(Request $request, $id)
    {
        $user = $this->trouver($id);
        $donnees = $this->valider($request, $user);
        $role = $this->profil($request->input('roles'));
        $avant = $user->only(array_keys($donnees)) + ['profil' => $user->roles->first()?->name];

        $donnees['flag_actif_users'] = $request->boolean('flag_actif_users');
        if ($request->filled('password')) {
            $donnees['password'] = Hash::make($request->input('password'));
            $donnees['flag_mdp'] = false;
        }
        if (Auth::user()->estSuperAdmin() && !app(AgenceCourante::class)->existe()) {
            $donnees['agence_id'] = $this->agenceCible($request);
        }

        $user->update($donnees);
        $profilActuel = $user->roles->first();
        $attente = $profilActuel?->id !== $role->id
            ? \App\Services\Validation\ValidationService::intercepter('UTILISATEUR_PROFIL', $user, ['role_id' => $role->id], 'Profil demandé : ' . $role->name, null, $role->name)
            : null;
        if (!$attente) {
            $user->syncRoles([$role]);
        }
        $this->synchroniserAgences($request, $user);
        Menu::oublierArbres();

        Audit::logSave(['action' => 'MODIFIER', 'code_piece' => $user->id, 'menu' => 'LISTE DES UTILISATEURS', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION',
            'objet_type' => 'users', 'objet_id' => $user->id, 'anciennes_valeurs' => $avant, 'nouvelles_valeurs' => collect($donnees)->except('password')->all() + ['profil' => $role->name]]);

        return redirect()->route('users.index')->with($attente ? 'info' : 'success', $attente
            ? 'Utilisateur modifié ; le changement de profil (' . $role->name . ') est soumis à validation : demande ' . $attente->numero . '.'
            : 'Utilisateur ' . $user->nom_complet . ' modifié.');
    }

    /** Déverrouillage d'un compte bloqué après des échecs de connexion. */
    public function deverrouiller($id)
    {
        $user = $this->trouver($id);
        $user->forceFill(['tentatives_echouees' => 0, 'verrouille_jusqu_a' => null])->saveQuietly();
        Audit::logSave(['action' => 'DEVERROUILLER', 'code_piece' => $user->id, 'menu' => 'LISTE DES UTILISATEURS', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION']);

        return back()->with('success', 'Compte ' . $user->login_users . ' déverrouillé.');
    }

    /** Utilisateurs visibles : ceux de l'agence courante ; tous pour le super administrateur hors agence. */
    protected function portee()
    {
        return Auth::user()->estSuperAdmin() && !app(AgenceCourante::class)->existe() ? User::query() : User::deLAgence();
    }

    protected function trouver($id): User
    {
        $cle = Crypt::UrldeCrypt($id) ?? (ctype_digit((string) $id) ? (int) $id : null);

        return $this->portee()->whereKey($cle ?? 0)->firstOrFail();
    }

    /**
     * Profils que l'utilisateur connecté peut attribuer : ceux de l'agence et les modèles (jamais SPADMIN hors super administrateur).
     */
    protected function rolesVisibles()
    {
        return Role::visiblesPour()->orderBy('name')->get();
    }

    /** Profil choisi (identifiant, ou « NOM/code » des anciens formulaires), limité aux profils visibles. */
    protected function profil($valeur): Role
    {
        $roles = $this->rolesVisibles();

        return (ctype_digit((string) $valeur) ? $roles->firstWhere('id', (int) $valeur) : $roles->firstWhere('name', explode('/', (string) $valeur)[0]))
            ?? abort(403, 'Profil non autorisé');
    }

    protected function valider(Request $request, ?User $user = null): array
    {
        $donnees = $request->validate([
            'name' => 'required|max:100',
            'prenom_users' => 'required|max:150',
            'login_users' => ['required', 'min:8', 'max:60', Rule::unique('users', 'login_users')->ignore($user?->id)],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'roles' => 'required',
            'password' => $user ? 'nullable|min:8' : 'required|min:8',
            'genre_users' => 'nullable|max:20',
            'cel_users' => 'nullable|max:30',
            'tel_users' => 'nullable|max:30',
            'adresse_users' => 'nullable|max:255',
            'fonction' => 'nullable|max:100',
            'date_debut_acces' => 'nullable|date',
            'date_fin_acces' => 'nullable|date|after_or_equal:date_debut_acces',
        ], [
            'login_users.min' => 'Un identifiant doit avoir un minimum de 8 caractères.',
            'login_users.unique' => 'Cet identifiant existe déjà.',
            'email.unique' => 'Cet e-mail est déjà utilisé.',
            'roles.required' => 'Veuillez sélectionner un profil.',
            'password.min' => 'Le mot de passe doit avoir au moins 8 caractères.',
        ]);

        return collect($donnees)->except(['roles', 'password'])->all();
    }

    /** Agence du compte : l'agence courante ; hors agence, celle choisie par le super administrateur (vide = compte plateforme). */
    protected function agenceCible(Request $request): ?int
    {
        $courante = app(AgenceCourante::class)->id();
        if ($courante || !Auth::user()->estSuperAdmin()) {
            return $courante ?? abort(403);
        }

        return $request->filled('agence_id') ? (int) Agence::findOrFail($request->integer('agence_id'))->id : null;
    }

    /** Agences supplémentaires (groupe possédant plusieurs agences) : attribuées par le super administrateur. */
    protected function synchroniserAgences(Request $request, User $user): void
    {
        if (Auth::user()->estSuperAdmin() && $request->has('agences_supplementaires')) {
            $ids = Agence::whereIn('id', array_map('intval', (array) $request->input('agences_supplementaires', [])))->pluck('id')->all();
            $user->agences()->sync(array_values(array_diff($ids, [$user->agence_id])));
        }
    }

    protected function donneesFormulaire(): array
    {
        return [
            'superAdmin' => Auth::user()->estSuperAdmin(),
            'horsAgence' => !app(AgenceCourante::class)->existe(),
            'agences' => Auth::user()->estSuperAdmin() ? Agence::orderBy('raison_sociale')->pluck('raison_sociale', 'id') : collect(),
        ];
    }

    /**
     * Envoie les identifiants par mail et SMS (en file d'attente).
     */
    protected function notifierCreation(User $user, string $motDePasse): void
    {
        $appli = @Menu::get_logo()->mot_cle ?: config('rbac.app_name');
        $nom = trim($user->name . ' ' . $user->prenom_users);

        try {
            // dans une agence : modèle de message « Création d'un compte utilisateur » et canaux configurés
            if (NotificationService::notifier('COMPTE_UTILISATEUR', ['email' => $user->email, 'telephone' => $user->cel_users, 'nom' => $nom],
                ['identifiant' => $user->login_users, 'mot_de_passe' => $motDePasse, 'lien' => url('/connexion')])) {
                return;
            }

            if ($user->email) {
                Email::envoyerEnFile($user->email, $nom,
                    "<b>Cher $nom,</b><br><br>Votre compte a été créé avec succès.<br><br>"
                    . '<b>Identifiant : </b>' . e($user->login_users) . '<br>'
                    . '<b>Mot de passe : </b>' . e($motDePasse) . '<br><br>'
                    . "Vous devrez changer ce mot de passe à la première connexion : <a href='" . url('/connexion') . "'>" . url('/connexion') . '</a>',
                    'Création de votre compte ' . $appli,
                    'Bienvenue sur ' . $appli);
            }

            if ($user->cel_users) {
                Envoisms::envoyerEnFile($user->cel_users,
                    "$appli - Identifiant : {$user->login_users} Mot de passe : $motDePasse Accès : " . url('/connexion'));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
