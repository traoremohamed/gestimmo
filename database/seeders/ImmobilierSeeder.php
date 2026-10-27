<?php

namespace Database\Seeders;

use App\Models\Menus;
use App\Models\Permissions;
use App\Models\Role;
use App\Models\Sousmenus;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menus, sous-menus et permissions des modules métier (config/immobilier.php)
 * et profils métier. Idempotent ; à lancer après RbacSeeder.
 *
 *   php artisan db:seed --class=ImmobilierSeeder
 */
class ImmobilierSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionsParModule = [];

        foreach (config('immobilier.modules') as $code => $module) {
            $menu = Menus::updateOrCreate(['menu' => mb_strtoupper($module['libelle'])], [
                'code' => $code,
                'module' => $code,
                'priorite_menu' => $module['priorite'],
                'icone' => '<i class="menu-icon ' . $module['icone'] . '"></i>',
                'is_valide' => true,
                'flag_is_admin' => false,
            ]);

            $permissionsParModule[$code] = $this->creerSousmenus($menu, $module['menus']);
        }

        // administration de l'agence, paramétrage métier et plateforme (comme le SIH et MARIEATTIEKESAAS)
        foreach ([
            'ADMINISTRATION' => [200, 'ti ti-shield-lock', 'menus_administration', false],
            'PARAMETRAGE' => [210, 'ti ti-adjustments', 'menus_parametrage', false],
            'PLATEFORME' => [220, 'ti ti-cloud', 'menus_plateforme', true],
        ] as $nom => [$priorite, $icone, $cle, $plateforme]) {
            $menu = Menus::updateOrCreate(['menu' => $nom], [
                'code' => $nom, 'priorite_menu' => $priorite, 'icone' => '<i class="menu-icon tf-icons ' . $icone . '"></i>', 'is_valide' => true, 'flag_is_admin' => $plateforme,
            ]);
            $this->creerSousmenus($menu, config('immobilier.' . $cle));
        }

        $toutesModules = array_merge(...array_values($permissionsParModule));

        foreach (config('immobilier.profils') as $nom => [$codeRole, $permissions]) {
            $noms = [];
            foreach ($permissions as $permission) {
                $noms = match (true) {
                    $permission === 'module:*' => [...$noms, ...$toutesModules],
                    str_starts_with($permission, 'module:') => [...$noms, ...($permissionsParModule[substr($permission, 7)] ?? [])],
                    default => [...$noms, $permission],
                };
            }

            // profils modèles : chaque agence les utilise tels quels ou crée les siens à partir d'eux
            $role = Role::updateOrCreate(['name' => $nom, 'guard_name' => 'web', 'agence_id' => null], ['code_roles' => $codeRole, 'codes_roles_detail' => $codeRole]);
            $this->attribuer($role, array_unique($noms));
        }

        // le super administrateur reçoit toutes les permissions
        if ($spadmin = Role::where('codes_roles_detail', Role::SPADMIN)->first()) {
            $this->attribuer($spadmin, Permissions::pluck('name')->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return string[] noms des permissions créées
     */
    protected function creerSousmenus(Menus $menu, array $sousmenus): array
    {
        $noms = [];
        foreach ($sousmenus as $definition) {
            [$route, $libelle, $priorite, $permissions] = $definition;
            $sousmenu = Sousmenus::updateOrCreate(['sousmenu' => $route], [
                'menu_id_menu' => $menu->id_menu, 'code' => $route, 'libelle' => $libelle, 'priorite_sousmenu' => $priorite, 'is_valide' => true,
                'flag_is_admin' => (bool) ($definition[4] ?? false), 'icone' => $definition[5] ?? config('rbac.icones.' . $route),
            ]);

            foreach ($permissions as $name => $lib) {
                Permissions::updateOrCreate(['name' => $name, 'guard_name' => 'web'], [
                    'lib_permission' => $lib, 'id_sousmenu' => $sousmenu->id_sousmenu, 'is_valide' => true,
                ]);
                $noms[] = $name;
            }
        }

        return $noms;
    }

    protected function attribuer(Role $role, array $permissions): void
    {
        $perms = Permissions::whereIn('name', $permissions)->get();
        $role->syncPermissions($perms);
        $role->sousmenus()->sync($perms->pluck('id_sousmenu')->filter()->unique()->values()->all());
    }
}
