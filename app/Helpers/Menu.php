<?php

namespace App\Helpers;

use App\Models\Logo;
use App\Support\AgenceCourante;
use Illuminate\Support\Facades\DB;

class Menu
{
    /**
     * Menus et sous-menus autorisés pour l'utilisateur, groupés par id_menu.
     */
    public static function get_menu($idutil): array
    {
        $roles = static::rolesQuery($idutil)->pluck('roles.id');

        if ($roles->isEmpty()) {
            return [];
        }

        // menus métier : seulement ceux des modules activés pour l'agence courante
        $modulesActifs = app(AgenceCourante::class)->agence()?->modulesActifs() ?? [];

        $resulat = DB::table('role_has_sousmenus')
            ->join('sousmenu', 'role_has_sousmenus.sousmenus_id_sousmenu', '=', 'sousmenu.id_sousmenu')
            ->join('menu', 'sousmenu.menu_id_menu', '=', 'menu.id_menu')
            ->whereIn('role_has_sousmenus.role_id', $roles)
            ->where('menu.is_valide', true)
            ->where('sousmenu.is_valide', true)
            ->where(fn ($q) => $q->whereNull('menu.module')->orWhereIn('menu.module', $modulesActifs))
            ->select('menu.id_menu', 'menu.menu', 'menu.icone', 'menu.priorite_menu',
                'sousmenu.id_sousmenu', 'sousmenu.sousmenu', 'sousmenu.libelle', 'sousmenu.priorite_sousmenu')
            ->distinct()
            ->orderBy('menu.priorite_menu')
            ->orderBy('sousmenu.priorite_sousmenu')
            ->get();

        $tabl = [];
        foreach ($resulat as $ligne) {
            $tabl[$ligne->id_menu][] = $ligne;
        }

        return $tabl;
    }

    /** @var array<int, array> */
    protected static array $arbres = [];

    /**
     * Arborescence des menus visibles par l'utilisateur, sur un nombre illimité de niveaux (repris du SIH).
     *
     * Un nœud est visible s'il est attribué à l'un des profils de l'utilisateur (page) ou si l'un de ses descendants
     * l'est (groupe). Les modules désactivés pour l'agence sont masqués. Le super administrateur voit tout dans une
     * agence ; hors agence, le menu PLATEFORME et les écrans d'administration utilisables sans agence
     * (config rbac.hors_agence : utilisateurs, profils, droits, journal, canaux d'envoi).
     *
     * @return array<int, array{id: int, code: ?string, menu: string, icone: ?string, noeuds: array}>
     */
    public static function arbre($idutil): array
    {
        if (isset(static::$arbres[$idutil])) {
            return static::$arbres[$idutil];
        }

        if (\App\Models\User::whereKey($idutil)->value('est_super_admin')) {
            $arbre = static::construire(null, true, [], true);
            $dansAgence = app(AgenceCourante::class)->existe();

            if (!$dansAgence) {
                $horsAgence = config('rbac.hors_agence', []);
                $arbre = array_values(array_filter(array_map(function ($m) use ($horsAgence) {
                    if ($m['code'] !== 'PLATEFORME') {
                        $m['noeuds'] = array_values(array_filter($m['noeuds'], fn ($n) => in_array($n['lien'], $horsAgence, true)));
                    }

                    return $m;
                }, $arbre), fn ($m) => in_array($m['code'], ['PLATEFORME', 'ADMINISTRATION', 'PARAMETRAGE'], true) && $m['noeuds'] !== []));
            }

            return static::$arbres[$idutil] = $arbre;
        }

        $roles = static::rolesQuery($idutil)->pluck('roles.id');
        if ($roles->isEmpty()) {
            return static::$arbres[$idutil] = [];
        }
        $autorises = DB::table('role_has_sousmenus')->whereIn('role_id', $roles)->pluck('sousmenus_id_sousmenu')->flip()->all();

        return static::$arbres[$idutil] = static::construire($autorises, false, [], true);
    }

    /**
     * Arborescence complète (écran des droits d'accès) : tous les nœuds valides avec leurs permissions,
     * limitée aux modules actifs de l'agence courante.
     */
    public static function arbreComplet(bool $inclureAdmin): array
    {
        $permissions = DB::table('permissions')->where('is_valide', true)->orderBy('id')->get()->groupBy('id_sousmenu');

        return static::construire(null, $inclureAdmin, $permissions->all(), app(AgenceCourante::class)->existe());
    }

    /**
     * @param array<int, mixed>|null $autorises  NULL = tous les nœuds
     */
    protected static function construire(?array $autorises, bool $inclureAdmin, array $permissions = [], bool $filtrerModules = false): array
    {
        $menus = DB::table('menu')->where('is_valide', true)
            ->when(!$inclureAdmin, fn ($q) => $q->where(fn ($w) => $w->where('flag_is_admin', false)->orWhereNull('flag_is_admin')))
            ->orderBy('priorite_menu')->orderBy('menu')->get();

        $noeuds = DB::table('sousmenu')->where('is_valide', true)
            ->when(!$inclureAdmin, fn ($q) => $q->where(fn ($w) => $w->where('flag_is_admin', false)->orWhereNull('flag_is_admin')))
            ->orderBy('priorite_sousmenu')->orderBy('libelle')->get();

        $enfants = [];
        foreach ($noeuds as $n) {
            $enfants[$n->parent_id ?? 'menu-' . $n->menu_id_menu][] = $n;
        }

        $construireNiveau = function ($cle, int $profondeur) use (&$construireNiveau, $enfants, $autorises, $permissions): array {
            $resultat = [];
            if ($profondeur > 25) {           // garde-fou contre une boucle de données
                return $resultat;
            }
            foreach ($enfants[$cle] ?? [] as $n) {
                $sous = $construireNiveau($n->id_sousmenu, $profondeur + 1);
                $lien = trim((string) $n->sousmenu) ?: null;
                $estAutorise = $autorises === null || ($lien && isset($autorises[$n->id_sousmenu]));
                if (!$estAutorise && $sous === []) {
                    continue;
                }
                $resultat[] = [
                    'id' => $n->id_sousmenu,
                    'libelle' => $n->libelle,
                    'lien' => $estAutorise ? $lien : null,   // page non attribuée mais parent de pages attribuées : simple groupe
                    'icone' => $n->icone,
                    'niveau' => $profondeur,
                    'admin' => (bool) $n->flag_is_admin,
                    'permissions' => $permissions[$n->id_sousmenu] ?? collect(),
                    'enfants' => $sous,
                ];
            }

            return $resultat;
        };

        $agence = app(AgenceCourante::class)->agence();
        $modulesActifs = $agence?->modulesActifs() ?? [];
        $arbre = [];
        foreach ($menus as $m) {
            if ($filtrerModules && $m->module && $agence && !in_array($m->module, $modulesActifs, true)) {
                continue;                                    // module désactivé pour l'agence
            }
            $racines = $construireNiveau('menu-' . $m->id_menu, 1);
            if ($racines !== [] || $autorises === null) {
                $arbre[] = ['id' => $m->id_menu, 'code' => $m->code, 'menu' => $m->menu, 'icone' => $m->icone, 'noeuds' => $racines];
            }
        }

        return $arbre;
    }

    /** Un nœud (ou l'un de ses descendants) correspond-il à la page courante ? */
    public static function noeudActif(array $noeud): bool
    {
        if ($noeud['lien'] && (request()->is($noeud['lien']) || request()->is($noeud['lien'] . '/*')
            || \Illuminate\Support\Str::startsWith((string) request()->route()?->getName(), $noeud['lien'] . '.'))) {
            return true;
        }
        foreach ($noeud['enfants'] as $enfant) {
            if (static::noeudActif($enfant)) {
                return true;
            }
        }

        return false;
    }

    public static function oublierArbres(): void
    {
        static::$arbres = [];
    }

    public static function get_id_profil($idutil)
    {
        return static::rolesQuery($idutil)->value('roles.id') ?? '';
    }

    public static function get_menu_profil($idutil)
    {
        return static::rolesQuery($idutil)->value('roles.name') ?? '';
    }

    public static function get_code_menu_profil($idutil)
    {
        return static::rolesQuery($idutil)->value('roles.code_roles') ?? '';
    }

    public static function get_code_menu_profil_tabl($idutil): array
    {
        return static::rolesQuery($idutil)->pluck('roles.code_roles')->toArray();
    }

    /**
     * Code détaillé du profil (SPADMIN pour le super administrateur).
     */
    public static function get_code_menu_profil_detail($idutil)
    {
        return static::rolesQuery($idutil)->value('roles.codes_roles_detail') ?? '';
    }

    public static function get_logo()
    {
        return Logo::actifs('LOGO')->first() ?? '';
    }

    public static function get_info_acceuil()
    {
        return Logo::actifs('IMAGE ACCEUIL')->first() ?? '';
    }

    public static function get_info_couleur()
    {
        return Logo::actifs('COULEUR MENU HAUT')->first() ?? '';
    }

    public static function get_info_image_dashboard()
    {
        return Logo::actifs('IMAGE DASHBORD')->get();
    }

    public static function get_info_reseaux()
    {
        return Logo::actifs('RESEAUX SOCIAUX')->get();
    }

    public static function dateEnFrancais($dateSend): string
    {
        return \Carbon\Carbon::parse($dateSend)->locale('fr')->translatedFormat('l d F Y');
    }

    protected static function rolesQuery($idutil)
    {
        return DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $idutil)
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->orderBy('roles.id');
    }
}
