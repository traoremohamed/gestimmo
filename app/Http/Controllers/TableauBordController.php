<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\Echeance;
use App\Models\Loyer;
use App\Models\Reclamation;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\Pilotage\Indicateurs;
use App\Support\AgenceCourante;
use Illuminate\Support\Facades\Auth;

/**
 * Tableau de bord : indicateurs de l'agence selon ses modules ; vue plateforme pour le super administrateur.
 * Un client (profil CLIENT) est redirigé vers son espace.
 */
class TableauBordController extends Controller
{
    public function index(AgenceCourante $agenceCourante, Indicateurs $indicateurs)
    {
        $user = Auth::user();

        if ($user->can('moncompte-index') && !$user->can('tiers-index') && $agenceCourante->moduleActif('espace')) {
            return redirect()->route('moncompte');
        }

        if (!$agenceCourante->existe()) {
            return view('dashboard.plateforme', [
                'agences' => Agence::withCount('users')->orderBy('raison_sociale')->get(),
                'utilisateurs' => User::count(),
            ]);
        }

        $modules = $agenceCourante->agence()->modulesActifs();

        return view('dashboard.agence', [
            'tuiles' => $indicateurs->tableauDeBord(),
            'graphique' => $indicateurs->encaissementsMensuels(),
            'rendezVous' => RendezVous::with(['tiers', 'bien'])->whereDate('debut', today())
                ->when(!$user->can('rendezvous-tous'), fn ($q) => $q->where('user_id', $user->id))->orderBy('debut')->get(),
            'echeances' => in_array('vente', $modules, true)
                ? Echeance::with('vente.acquereur')->enRetard()->whereHas('vente', fn ($q) => $q->where('statut', 'en_cours'))->orderBy('date_echeance')->limit(6)->get()
                : collect(),
            'loyers' => in_array('location', $modules, true)
                ? Loyer::with('bail.locataire')->enRetard()->orderBy('date_echeance')->limit(6)->get()
                : collect(),
            'reclamations' => Reclamation::with('tiers')->whereIn('statut', ['ouverte', 'en_cours'])->orderByRaw("case priorite when 'urgente' then 0 when 'haute' then 1 else 2 end")->limit(6)->get(),
        ]);
    }
}
