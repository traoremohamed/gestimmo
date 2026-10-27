<?php

namespace App\Http\Controllers\Vente;

use App\Http\Controllers\Controller;
use App\Models\Echeance;
use Illuminate\Http\Request;

/**
 * Échéances des contrats en cours : à venir, en retard (relances).
 */
class EcheanceController extends Controller
{
    public function index(Request $request)
    {
        $filtre = $request->query('filtre', 'retard');

        $echeances = Echeance::with('vente.acquereur')
            ->impayes()
            ->whereHas('vente', fn ($q) => $q->where('statut', 'en_cours'))
            ->when($filtre === 'retard', fn ($q) => $q->whereDate('date_echeance', '<', today()))
            ->when($filtre === 'mois', fn ($q) => $q->whereBetween('date_echeance', [today()->startOfMonth(), today()->endOfMonth()]))
            ->when($filtre === '30j', fn ($q) => $q->whereBetween('date_echeance', [today(), today()->addDays(30)]))
            ->orderBy('date_echeance')
            ->paginate(50)->withQueryString();

        $totalRetard = Echeance::enRetard()->whereHas('vente', fn ($q) => $q->where('statut', 'en_cours'))
            ->selectRaw('coalesce(sum(montant - montant_paye), 0) as total')->value('total');

        return view('vente.echeances', compact('echeances', 'filtre', 'totalRetard'));
    }
}
