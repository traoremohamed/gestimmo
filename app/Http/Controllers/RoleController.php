<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Helpers\Crypt;
use App\Models\Permissions;
use App\Models\Role;
use App\Support\AgenceCourante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Profils (repris de MARIEATTIEKESAAS) : chaque agence voit ses profils et les profils modèles de la plateforme.
 * Un modèle n'est modifiable que par le super administrateur : l'agence crée son propre profil à partir du modèle.
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::visiblesPour()->with('agence')->withCount(['permissions', 'users', 'sousmenus'])->orderByRaw('agence_id IS NULL')->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.form', ['role' => new Role(), 'modeles' => Role::visiblesPour()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $agence = app(AgenceCourante::class)->id();
        abort_if($agence === null && !Auth::user()->estSuperAdmin(), 403);
        $request->validate([
            'name' => ['required', 'max:125', Rule::unique('roles', 'name')->where(fn ($q) => $agence ? $q->where('agence_id', $agence) : $q->whereNull('agence_id'))],
            'description' => 'nullable|max:255',
            'modele_id' => 'nullable|integer',
        ], ['name.unique' => 'Un profil porte déjà ce nom.']);

        $role = DB::transaction(function () use ($request, $agence) {
            $role = Role::create(['name' => mb_strtoupper(trim($request->name)), 'guard_name' => 'web', 'agence_id' => $agence, 'description' => $request->description]);
            // partir d'un profil existant (modèle de la plateforme ou profil de l'agence)
            if ($request->filled('modele_id') && ($modele = Role::visiblesPour()->find($request->integer('modele_id')))) {
                $role->syncPermissions($modele->permissions);
                $role->sousmenus()->sync($modele->sousmenus()->pluck('sousmenu.id_sousmenu')->all());
            }

            return $role;
        });

        Audit::logSave(['action' => 'CREER', 'code_piece' => $role->id, 'menu' => 'LISTE DES PROFILS', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION',
            'objet_type' => 'roles', 'objet_id' => $role->id, 'nouvelles_valeurs' => ['name' => $role->name, 'modele' => $request->modele_id]]);

        return redirect()->route('menuprofillayout', Crypt::UrlCrypt($role->id))
            ->with('success', 'Profil ' . $role->name . ' créé. Attribuez maintenant ses droits d\'accès.');
    }

    public function show($id)
    {
        $role = Role::visiblesPour()->findOrFail(Crypt::UrldeCrypt($id) ?? abort(404));
        $rolePermissions = Permissions::join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('role_has_permissions.role_id', $role->id)->with('sousmenu')->get();

        return view('roles.show', compact('role', 'rolePermissions'));
    }

    public function edit($id)
    {
        return view('roles.form', ['role' => $this->profilModifiable($id), 'modeles' => collect()]);
    }

    public function update(Request $request, $id)
    {
        $role = $this->profilModifiable($id);
        $request->validate([
            'name' => ['required', 'max:125', Rule::unique('roles', 'name')->ignore($role->id)
                ->where(fn ($q) => $role->agence_id ? $q->where('agence_id', $role->agence_id) : $q->whereNull('agence_id'))],
            'description' => 'nullable|max:255',
        ]);
        $avant = $role->only(['name', 'description']);
        $role->update(['name' => mb_strtoupper(trim($request->input('name'))), 'description' => $request->description]);

        Audit::logSave(['action' => 'MODIFIER', 'code_piece' => $role->id, 'menu' => 'LISTE DES PROFILS', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION',
            'objet_type' => 'roles', 'objet_id' => $role->id, 'anciennes_valeurs' => $avant, 'nouvelles_valeurs' => $role->only(['name', 'description'])]);

        return redirect()->route('roles.index')->with('success', 'Profil modifié.');
    }

    /** Un profil modèle de la plateforme n'est modifiable que par le super administrateur. */
    protected function profilModifiable($id): Role
    {
        $role = Role::visiblesPour()->findOrFail(Crypt::UrldeCrypt($id) ?? abort(404));
        abort_if($role->estModele() && !Auth::user()->estSuperAdmin(), 403, 'Profil modèle de la plateforme : créez un profil à partir de ce modèle pour l\'adapter.');

        return $role;
    }
}
