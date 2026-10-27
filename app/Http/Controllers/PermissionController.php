<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Helpers\Crypt;
use App\Models\Permissions;
use App\Models\Sousmenus;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        $data = Permissions::with('sousmenu')->orderBy('id')->get();

        return view('permissions.index', compact('data'));
    }

    public function create()
    {
        $SousMenuList = "<option value=''> -- Sélectionner --</option>";
        foreach (Sousmenus::with('menu')->where('is_valide', true)->orderBy('libelle')->get() as $comp) {
            $SousMenuList .= "<option value='" . $comp->id_sousmenu . "'>" . e(strtoupper($comp->libelle)) . ' [ ' . e($comp->menu?->menu) . ' ]</option>';
        }

        return view('permissions.create', compact('SousMenuList'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name',
            'lib_permission' => 'required',
            'id_sousmenu' => 'required|exists:sousmenu,id_sousmenu',
        ]);

        $permission = Permissions::create([
            'name' => $request->input('name'),
            'lib_permission' => $request->input('lib_permission'),
            'id_sousmenu' => $request->input('id_sousmenu'),
            'is_valide' => $request->boolean('is_valide'),
            'guard_name' => 'web',
        ]);

        Audit::logSave(['action' => 'CREER', 'code_piece' => $permission->id, 'menu' => 'PERMISSIONS', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION']);

        return redirect()->route('permissions.index')->with('success', 'Succes : Enregistrement réussi.');
    }

    public function edit($id)
    {
        $permission = Permissions::findOrFail(Crypt::UrldeCrypt($id));

        $SousMenuList = "<option value='" . $permission->id_sousmenu . "'>" . e($permission->sousmenu?->libelle) . '</option>';
        foreach (Sousmenus::with('menu')->where('is_valide', true)->orderBy('libelle')->get() as $comp) {
            if ($comp->id_sousmenu != $permission->id_sousmenu) {
                $SousMenuList .= "<option value='" . $comp->id_sousmenu . "'>" . e(strtoupper($comp->libelle)) . ' [ ' . e($comp->menu?->menu) . ' ]</option>';
            }
        }

        return view('permissions.edit', compact('permission', 'SousMenuList'));
    }

    public function update(Request $request, Permissions $permission)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name,' . $permission->id,
            'lib_permission' => 'required',
            'id_sousmenu' => 'required|exists:sousmenu,id_sousmenu',
        ]);

        $permission->update([
            'name' => $request->input('name'),
            'lib_permission' => $request->input('lib_permission'),
            'id_sousmenu' => $request->input('id_sousmenu'),
            'is_valide' => $request->boolean('is_valide'),
        ]);

        Audit::logSave(['action' => 'MODIFIER', 'code_piece' => $permission->id, 'menu' => 'PERMISSIONS', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION']);

        return redirect()->route('permissions.index')->with('success', 'Succes : Mise à jour réussie.');
    }
}
