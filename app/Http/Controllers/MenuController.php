<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Models\Menus;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        $data = Menus::orderBy('priorite_menu')->get();

        return view('menus.index', compact('data'));
    }

    public function create()
    {
        return view('menus.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'menu' => 'required',
            'priorite_menu' => 'required|integer',
        ]);

        $menu = Menus::create([
            'menu' => $request->input('menu'),
            'icone' => $request->input('icone'),
            'priorite_menu' => $request->input('priorite_menu'),
            'is_valide' => $request->boolean('is_valide'),
        ]);

        Audit::logSave(['action' => 'CREER', 'code_piece' => $menu->id_menu, 'menu' => 'LISTE DES MODULES', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION']);

        return redirect()->route('menus.index')->with('success', 'Succes : Enregistrement réussi.');
    }

    public function edit(Menus $menu)
    {
        return view('menus.edit', compact('menu'));
    }

    public function update(Request $request, Menus $menu)
    {
        $request->validate([
            'menu' => 'required',
            'priorite_menu' => 'required|integer',
        ]);

        $menu->update([
            'menu' => $request->input('menu'),
            'icone' => $request->input('icone'),
            'priorite_menu' => $request->input('priorite_menu'),
            'is_valide' => $request->boolean('is_valide'),
        ]);

        Audit::logSave(['action' => 'MODIFIER', 'code_piece' => $menu->id_menu, 'menu' => 'LISTE DES MODULES', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION']);

        return redirect()->route('menus.index')->with('success', 'Succes : Enregistrement réussi.');
    }
}
