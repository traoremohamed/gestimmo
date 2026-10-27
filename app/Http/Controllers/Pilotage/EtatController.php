<?php

namespace App\Http\Controllers\Pilotage;

use App\Http\Controllers\Controller;
use App\Services\Pilotage\Indicateurs;
use App\Support\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EtatController extends Controller
{
    public function __construct(protected Indicateurs $indicateurs)
    {
    }

    public function index()
    {
        return view('pilotage.etats', ['rapports' => $this->indicateurs->disponibles()]);
    }

    public function show(Request $request, string $rapport)
    {
        abort_unless(array_key_exists($rapport, $this->indicateurs->disponibles()), 404);

        $du = $request->filled('du') ? Carbon::parse($request->du) : today()->startOfYear();
        $au = $request->filled('au') ? Carbon::parse($request->au) : today();
        $donnees = $this->indicateurs->rapport($rapport, $du, $au);

        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            return Export::telecharger($request->query('format'), $donnees['titre'] ?? $this->indicateurs->disponibles()[$rapport][0], $donnees['colonnes'], $donnees['lignes'],
                $donnees['total'] ?? null, 'Du ' . $du->format('d/m/Y') . ' au ' . $au->format('d/m/Y'));
        }

        return view('pilotage.etat', compact('donnees', 'rapport', 'du', 'au'));
    }
}
