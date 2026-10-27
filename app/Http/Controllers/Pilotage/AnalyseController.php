<?php

namespace App\Http\Controllers\Pilotage;

use App\Http\Controllers\Controller;
use App\Services\Pilotage\AnalyseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyseController extends Controller
{
    public function __construct(protected AnalyseService $analyse)
    {
    }

    public function index(Request $request)
    {
        $du = $request->filled('du') ? Carbon::parse($request->du) : today()->startOfYear();
        $au = $request->filled('au') ? Carbon::parse($request->au) : today();
        $this->analyse->periode($du, $au);

        return view('pilotage.analyse', [
            'du' => $du, 'au' => $au,
            'precedente' => $this->analyse->precedente(),
            'indicateurs' => $this->analyse->indicateurs(),
            'tendance' => $this->analyse->tendance(),
            'prevision' => $this->analyse->prevision(),
            'risques' => $this->analyse->risqueClients(),
            'programmes' => $this->analyse->programmes(),
            'biens' => $this->analyse->rentabiliteBiens(),
            'recommandations' => $this->analyse->recommandations(),
        ]);
    }

    public function simulateur(Request $request)
    {
        $hypotheses = $request->validate([
            'recouvrement' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hausse_loyers' => ['nullable', 'numeric', 'min:-50', 'max:100'],
            'occupation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'remise_stock' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'ventes_stock' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]) + ['recouvrement' => 50, 'hausse_loyers' => 0, 'occupation' => (float) parametre('analyse.objectif_occupation'), 'remise_stock' => 0, 'ventes_stock' => 20];

        return view('pilotage.simulateur', ['h' => array_map('floatval', $hypotheses), 'simulation' => $this->analyse->simuler(array_map('floatval', $hypotheses))]);
    }
}
