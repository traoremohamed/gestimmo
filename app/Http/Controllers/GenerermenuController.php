<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Helpers\Crypt;
use App\Helpers\Menu;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Attribution des sous-menus et des permissions à un profil (droits d'accès), sur une arborescence de menus
 * de profondeur illimitée (repris de MARIEATTIEKESAAS). Un profil d'agence ne reçoit jamais les écrans de la
 * plateforme ; seuls les modules actifs de l'agence sont proposés.
 */
class GenerermenuController extends Controller
{
    public function parametragemenu()
    {
        $naroles = Menu::get_menu_profil(Auth::id());
        $roles = Role::visiblesPour()->with('agence')->withCount(['permissions', 'sousmenus', 'users'])->orderByRaw('agence_id IS NULL')->orderBy('name')->get();

        return view('generer.generer', compact('roles', 'naroles'));
    }

    public function menuprofillayout(Request $request, $id)
    {
        $role = Role::visiblesPour()->findOrFail(Crypt::UrldeCrypt($id) ?? (ctype_digit((string) $id) ? (int) $id : abort(404)));
        $superAdmin = Auth::user()->estSuperAdmin();
        $lectureSeule = ($role->estModele() && !$superAdmin) || $role->estSuperAdmin();
        // écrans de la plateforme : seulement pour un profil modèle édité par le super administrateur
        $inclureAdmin = $superAdmin && $role->estModele();

        if ($request->isMethod('post')) {
            abort_if($lectureSeule, 403, 'Profil modèle de la plateforme : créez un profil à partir de ce modèle pour l\'adapter.');
            $sousmenus = array_map('intval', array_values((array) $request->input('route', [])));
            $permissions = array_map('intval', array_values((array) $request->input('permission', [])));

            // seules les permissions d'écrans autorisés pour ce profil peuvent être accordées
            $permissions = DB::table('permissions as p')->join('sousmenu as s', 's.id_sousmenu', '=', 'p.id_sousmenu')
                ->when(!$inclureAdmin, fn ($q) => $q->where(fn ($w) => $w->where('s.flag_is_admin', false)->orWhereNull('s.flag_is_admin')))
                ->whereIn('p.id', $permissions)->pluck('p.id')->map(fn ($v) => (int) $v)->all();

            // une permission accordée rend visible la page qui la porte
            $sousmenus = array_values(array_unique(array_merge($sousmenus,
                DB::table('permissions')->whereIn('id', $permissions)->whereNotNull('id_sousmenu')->pluck('id_sousmenu')->map(fn ($v) => (int) $v)->all())));
            $autorises = DB::table('sousmenu')
                ->when(!$inclureAdmin, fn ($q) => $q->where(fn ($w) => $w->where('flag_is_admin', false)->orWhereNull('flag_is_admin')))
                ->whereIn('id_sousmenu', $sousmenus)->pluck('id_sousmenu')->all();

            $avant = ['sousmenus' => $role->sousmenus()->pluck('sousmenu.id_sousmenu')->all(), 'permissions' => $role->permissions()->pluck('id')->all()];
            DB::transaction(function () use ($role, $autorises, $permissions) {
                $role->sousmenus()->sync($autorises);
                $role->syncPermissions($permissions);
            });
            Menu::oublierArbres();

            Audit::logSave(['action' => 'ATTRIBUER', 'code_piece' => $role->id, 'menu' => 'DROITS D\'ACCES', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION',
                'objet_type' => 'roles', 'objet_id' => $role->id, 'anciennes_valeurs' => $avant, 'nouvelles_valeurs' => ['sousmenus' => $autorises, 'permissions' => $permissions]]);

            return redirect()->route('menuprofil')->with('success', 'Droits du profil ' . $role->name . ' enregistrés.');
        }

        $arbre = Menu::arbreComplet($inclureAdmin);
        $roleSousmenus = DB::table('role_has_sousmenus')->where('role_id', $role->id)->pluck('sousmenus_id_sousmenu')->flip()->all();
        $rolePermissions = DB::table('role_has_permissions')->where('role_id', $role->id)->pluck('permission_id')->flip()->all();

        return view('generer.menuprofillayout', compact('role', 'arbre', 'roleSousmenus', 'rolePermissions', 'lectureSeule'));
    }
}
