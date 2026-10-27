<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\Referentiel;
use App\Models\Rh\Avance;
use App\Models\Rh\Employe;
use App\Services\Rh\RhService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvanceController extends Controller
{
    public function index(Request $request)
    {
        return view('rh.avances', [
            'avances' => Avance::with('employe')->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))->latest('date_octroi')->paginate(30)->withQueryString(),
            'employes' => Employe::where('statut', 'actif')->orderBy('nom')->get()->pluck('nom_complet', 'id'),
            'modes' => Referentiel::options('mode_paiement'),
            'encours' => Avance::where('statut', 'en_cours')->get()->sum(fn ($a) => $a->reste()),
        ]);
    }

    public function store(Request $request, RhService $service)
    {
        $donnees = $request->validate([
            'employe_id' => ['required', Rule::exists('rh_employes', 'id')->where('agence_id', agence()->id)],
            'type' => ['required', Rule::in(['avance', 'pret'])],
            'montant' => ['required', 'numeric', 'gt:0'],
            'nombre_echeances' => ['required', 'integer', 'min:1', 'max:60'],
            'date_octroi' => ['required', 'date'],
            'mode_paiement' => ['required', Rule::in(Referentiel::options('mode_paiement')->keys())],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        $employe = Employe::findOrFail($donnees['employe_id']);
        if ($attente = $this->soumettre('AVANCE_SALAIRE', $employe, $donnees, $donnees['motif'] ?? null, $donnees['type'], (float) $donnees['montant'])) {
            return $attente;
        }
        $avance = $service->accorderAvance($employe, $donnees);

        return back()->with('success', ucfirst($avance->type) . ' ' . $avance->numero . ' accordé(e) : ' . montant($avance->montant_echeance) . ' retenus par mois.');
    }
}
