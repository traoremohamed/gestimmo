<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Models\Menus;
use App\Models\Sousmenus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Sous-modules du menu, imbricables sur autant de niveaux que souhaité (demande D1).
 * Un sous-menu sans lien est un groupe ; un sous-menu avec lien est une page.
 */
class SousmenuController extends Controller
{
    public function index()
    {
        $menus = Menus::orderBy('priorite_menu')->get();
        $tous = Sousmenus::withCount('permissions')->orderBy('priorite_sousmenu')->orderBy('libelle')->get();
        $parParent = $tous->groupBy(fn ($s) => $s->parent_id ?? 'menu-' . $s->menu_id_menu);

        return view('sousmenus.index', compact('menus', 'parParent'));
    }

    public function create(Request $request)
    {
        $sousmenus = new Sousmenus([
            'is_valide' => true,
            'parent_id' => $request->integer('parent') ?: null,
            'priorite_sousmenu' => 1,
        ]);
        if ($sousmenus->parent_id) {
            $sousmenus->menu_id_menu = Sousmenus::find($sousmenus->parent_id)?->menu_id_menu;
        }

        return view('sousmenus.create', $this->donneesFormulaire($sousmenus));
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $sousmenu = Sousmenus::create($donnees);

        Audit::logSave(['action' => 'CREER', 'code_piece' => $sousmenu->id_sousmenu, 'menu' => 'SOUS MODULES', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION',
            'objet_type' => 'sousmenu', 'objet_id' => $sousmenu->id_sousmenu, 'nouvelles_valeurs' => $donnees]);

        \App\Helpers\Menu::oublierArbres();

        return redirect()->route('sousmenus.index')->with('success', 'Sous-menu enregistré.');
    }

    public function edit(Sousmenus $sousmenu)
    {
        return view('sousmenus.edit', $this->donneesFormulaire($sousmenu));
    }

    public function update(Request $request, Sousmenus $sousmenu)
    {
        $donnees = $this->valider($request, $sousmenu);
        $avant = $sousmenu->only(array_keys($donnees));

        DB::transaction(function () use ($sousmenu, $donnees) {
            $sousmenu->update($donnees);
            // les descendants suivent leur parent s'il change de module
            if ($descendants = $sousmenu->descendantsIds()) {
                Sousmenus::whereIn('id_sousmenu', $descendants)->update(['menu_id_menu' => $sousmenu->menu_id_menu]);
            }
        });

        Audit::logSave(['action' => 'MODIFIER', 'code_piece' => $sousmenu->id_sousmenu, 'menu' => 'SOUS MODULES', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION',
            'objet_type' => 'sousmenu', 'objet_id' => $sousmenu->id_sousmenu, 'anciennes_valeurs' => $avant, 'nouvelles_valeurs' => $donnees]);

        \App\Helpers\Menu::oublierArbres();

        return redirect()->route('sousmenus.index')->with('success', 'Sous-menu enregistré.');
    }

    protected function valider(Request $request, ?Sousmenus $sousmenu = null): array
    {
        $request->merge(['parent_id' => $request->integer('parent') ?: null]);

        $request->validate([
            'menus' => 'required|exists:menu,id_menu',
            'parent_id' => 'nullable|exists:sousmenu,id_sousmenu',
            'libelle' => 'required|max:255',
            'sousmenu' => ['nullable', 'max:255', 'regex:/^[A-Za-z0-9\/_\-.]+$/'],
            'code' => ['nullable', 'max:60', Rule::unique('sousmenu', 'code')->ignore($sousmenu?->id_sousmenu, 'id_sousmenu')],
            'icone' => 'nullable|max:255',
            'priorite_sousmenu' => 'nullable|integer|min:0',
        ], [
            'sousmenu.regex' => 'Le lien ne doit contenir que des lettres, chiffres, /, -, _ et .',
        ]);

        $parentId = $request->input('parent_id');
        $menuId = (int) $request->input('menus');

        if ($parentId && $sousmenu) {
            abort_if((int) $parentId === $sousmenu->id_sousmenu || in_array((int) $parentId, $sousmenu->descendantsIds(), true), 422,
                'Un sous-menu ne peut pas être placé sous lui-même ou sous l\'un de ses descendants.');
        }
        if ($parentId) {
            // un enfant appartient toujours au module de son parent
            $menuId = (int) Sousmenus::find($parentId)->menu_id_menu;
        }

        return [
            'menu_id_menu' => $menuId,
            'parent_id' => $parentId,
            'code' => $request->input('code') ?: null,
            'sousmenu' => trim((string) $request->input('sousmenu'), '/ ') ?: null,
            'libelle' => $request->input('libelle'),
            'icone' => $request->input('icone'),
            'description' => $request->input('description'),
            'priorite_sousmenu' => (int) $request->input('priorite_sousmenu', 0),
            'is_valide' => $request->boolean('is_valide'),
            'flag_is_admin' => $request->boolean('flag_is_admin'),
        ];
    }

    protected function donneesFormulaire(Sousmenus $sousmenus): array
    {
        $exclus = $sousmenus->exists ? array_merge([$sousmenus->id_sousmenu], $sousmenus->descendantsIds()) : [];
        $menus = Menus::orderBy('priorite_menu')->get();
        $tous = Sousmenus::orderBy('priorite_sousmenu')->orderBy('libelle')->get();
        $parParent = $tous->groupBy(fn ($s) => $s->parent_id ?? 'menu-' . $s->menu_id_menu);

        // liste des parents possibles, indentée selon la profondeur
        $parents = [];
        $parcourir = function ($cle, int $profondeur, string $module) use (&$parcourir, $parParent, $exclus, &$parents) {
            foreach ($parParent[$cle] ?? [] as $s) {
                if (in_array($s->id_sousmenu, $exclus, true)) {
                    continue;
                }
                $parents[] = ['hid' => $s->id_sousmenu, 'menu' => $s->menu_id_menu, 'module' => $module,
                    'libelle' => str_repeat('— ', $profondeur) . $s->libelle . ($s->estGroupe() ? ' (groupe)' : '')];
                $parcourir($s->id_sousmenu, $profondeur + 1, $module);
            }
        };
        foreach ($menus as $m) {
            $parcourir('menu-' . $m->id_menu, 0, $m->menu);
        }

        return compact('sousmenus', 'menus', 'parents');
    }
}
