<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\Rh\Employe;
use App\Models\Rh\Presence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Pointage journalier : saisie groupée pour une date, synthèse du mois.
 */
class PresenceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();
        $employes = Employe::where('statut', 'actif')->orderBy('nom')->get();
        $presences = Presence::whereDate('date', $date)->get()->keyBy('employe_id');

        $mois = Presence::whereBetween('date', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])->get()->groupBy('employe_id');

        return view('rh.presences', compact('date', 'employes', 'presences', 'mois'));
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'lignes' => ['required', 'array'],
            'lignes.*.statut' => ['required', Rule::in(array_keys(Presence::STATUTS))],
            'lignes.*.heure_arrivee' => ['nullable', 'date_format:H:i'],
            'lignes.*.heure_depart' => ['nullable', 'date_format:H:i'],
            'lignes.*.heures_supplementaires' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'lignes.*.commentaire' => ['nullable', 'string', 'max:255'],
        ]);

        $ids = Employe::whereIn('id', array_keys($donnees['lignes']))->pluck('id')->all();
        foreach ($donnees['lignes'] as $employeId => $ligne) {
            if (!in_array((int) $employeId, $ids, true)) {
                continue;
            }
            Presence::updateOrCreate(['employe_id' => $employeId, 'date' => $donnees['date']], $ligne + ['heures_supplementaires' => 0, 'agence_id' => agence()->id]);
        }

        return back()->with('success', 'Pointage du ' . Carbon::parse($donnees['date'])->format('d/m/Y') . ' enregistré.');
    }
}
