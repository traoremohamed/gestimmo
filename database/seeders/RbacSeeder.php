<?php

namespace Database\Seeders;

use App\Models\Logo;
use App\Models\Menus;
use App\Models\Permissions;
use App\Models\Role;
use App\Models\Sousmenus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Données de base du module CONFIGURATION, identiques à CAPAVENIRJEUNESCI :
 * modules, sous-modules, permissions, profils, super administrateur et paramètres système.
 * Idempotent : peut être relancé sans créer de doublons.
 *
 *   php artisan db:seed --class=RbacSeeder
 */
class RbacSeeder extends Seeder
{
    /**
     * module => [priorité, icône, [sous-menus], réservé à la plateforme ?]
     * sous-menu => [route, libellé, priorité, [permission => libellé], écran de la plateforme ?]
     * Comme dans le SIH et MARIEATTIEKESAAS, l'ancien menu CONFIGURATION est éclaté :
     *  - ADMINISTRATION : écrans de l'agence (utilisateurs, profils, droits, journal) — complété par ImmobilierSeeder ;
     *  - PLATEFORME     : structure des menus, permissions, identité — jamais attribuée à un profil d'agence.
     */
    protected function structure(): array
    {
        return [
            'ADMINISTRATION' => [200, '<i class="menu-icon tf-icons ti ti-shield-lock"></i>', [
                ['users', 'Utilisateurs', 1, ['users-index' => 'Voir', 'users-create' => 'Créer', 'users-edit' => 'Modifier']],
                ['roles', 'Profils', 2, ['role-index' => 'Voir', 'role-create' => 'Créer', 'role-edit' => 'Modifier']],
                ['menuprofil', "Droits d'accès", 3, ['attribuer' => 'Attribuer les menus et permissions']],
                ['audits', "Journal d'audit", 9, ['audit-index' => 'Voir']],
            ]],
            'PLATEFORME' => [220, '<i class="menu-icon tf-icons ti ti-cloud"></i>', [
                ['parametresysteme', 'Identité de la plateforme', 3, ['parametresysteme-index' => 'Voir', 'parametresysteme-create' => 'Créer', 'parametresysteme-edit' => 'Modifier'], true],
                ['menus', 'Menus', 4, ['module-index' => 'Voir', 'module-create' => 'Créer', 'module-edit' => 'Modifier'], true],
                ['sousmenus', 'Sous-menus', 5, ['sous-module-index' => 'Voir', 'sous-module-create' => 'Créer', 'sous-module-edit' => 'Modifier'], true],
                ['permissions', 'Permissions', 6, ['permission-index' => 'Voir', 'permission-create' => 'Créer', 'permission-edit' => 'Modifier'], true],
            ], true],
        ];
    }

    /**
     * Profils : [code_roles, codes_roles_detail, permissions (* = toutes)]
     */
    protected function profils(): array
    {
        return [
            'SUPER ADMINISTRATEUR' => ['ADMIN', Role::SPADMIN, ['*']],
            'ADMINISTRATEUR' => ['ADM', 'ADM', [
                'role-index', 'role-create', 'role-edit', 'attribuer',
                'users-index', 'users-create', 'users-edit',
                'audit-index',
            ]],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->structure() as $nomMenu => $definitionMenu) {
            [$priorite, $icone, $sousmenus] = $definitionMenu;
            $menu = Menus::updateOrCreate(['menu' => $nomMenu], [
                'code' => $nomMenu, 'priorite_menu' => $priorite, 'icone' => $icone, 'is_valide' => true, 'flag_is_admin' => (bool) ($definitionMenu[3] ?? false),
            ]);

            foreach ($sousmenus as $definition) {
                [$route, $libelle, $prio, $permissions] = $definition;
                $sousmenu = Sousmenus::updateOrCreate(['sousmenu' => $route], [
                    'menu_id_menu' => $menu->id_menu, 'code' => $route, 'libelle' => $libelle, 'priorite_sousmenu' => $prio, 'is_valide' => true,
                    'flag_is_admin' => (bool) ($definition[4] ?? false), 'icone' => config('rbac.icones.' . $route),
                ]);

                foreach ($permissions as $name => $lib) {
                    Permissions::updateOrCreate(['name' => $name, 'guard_name' => 'web'], [
                        'lib_permission' => $lib, 'id_sousmenu' => $sousmenu->id_sousmenu, 'is_valide' => true,
                    ]);
                }
            }
        }

        // ancien menu CONFIGURATION : ses écrans ont été répartis entre ADMINISTRATION et PLATEFORME
        foreach (Menus::where('menu', 'CONFIGURATION')->get() as $ancien) {
            if (!Sousmenus::where('menu_id_menu', $ancien->id_menu)->exists()) {
                $ancien->delete();
            }
        }

        foreach ($this->profils() as $nom => [$code, $detail, $permissions]) {
            // profils modèles de la plateforme (agence_id vide)
            $role = Role::updateOrCreate(['name' => $nom, 'guard_name' => 'web', 'agence_id' => null], [
                'code_roles' => $code, 'codes_roles_detail' => $detail,
            ]);

            $perms = $permissions === ['*'] ? Permissions::all() : Permissions::whereIn('name', $permissions)->get();
            $role->syncPermissions($perms);

            // sous-menus visibles = ceux dont le profil a au moins une permission
            $role->sousmenus()->sync($perms->pluck('id_sousmenu')->filter()->unique()->values()->all());
        }

        $admin = User::firstOrNew(['login_users' => config('rbac.super_admin.login')]);
        if (!$admin->exists) {
            $admin->fill([
                'name' => 'SUPER',
                'prenom_users' => 'Administrateur',
                'email' => config('rbac.super_admin.email'),
                'password' => Hash::make(config('rbac.super_admin.password')),
                'flag_actif_users' => true,
                'flag_admin_users' => true, // masqué dans la liste des utilisateurs
                'flag_mdp' => false,        // changement obligatoire à la première connexion
            ])->save();
        }
        $admin->forceFill(['est_super_admin' => true, 'agence_id' => null])->save();
        $admin->syncRoles([Role::where('name', 'SUPER ADMINISTRATEUR')->whereNull('agence_id')->firstOrFail()]);

        foreach ([
            ['LOGO', config('rbac.app_name')],
            ['COULEUR MENU HAUT', '01619b'],
        ] as [$type, $valeur]) {
            Logo::firstOrCreate(['valeur' => $type], [
                'titre_logo' => config('rbac.app_name'), 'mot_cle' => $valeur, 'flag_logo' => true, 'id_user' => $admin->id,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
