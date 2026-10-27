<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Audit;
use App\Helpers\Menu;
use App\Http\Controllers\Controller;
use App\Services\AgenceService;
use App\Support\AgenceCourante;
use Illuminate\Http\Request;

/**
 * Administration › Modules activés : l'agence active les modules souscrits auprès de la plateforme.
 * Les modules « socle » restent toujours actifs ; un module désactivé disparaît du menu de tous les utilisateurs.
 */
class ModuleController extends Controller
{
    public function __construct(protected AgenceCourante $agenceCourante, protected AgenceService $service)
    {
    }

    public function index()
    {
        $agence = $this->agenceCourante->agence();

        return view('admin.modules', [
            'agence' => $agence,
            'modules' => config('immobilier.modules'),
            'actifs' => $agence->modulesActifs(),
            'souscrits' => $this->souscrits(),
        ]);
    }

    public function update(Request $request)
    {
        $agence = $this->agenceCourante->agence();
        $demandes = $request->validate(['modules' => ['nullable', 'array'], 'modules.*' => ['string']])['modules'] ?? [];
        $avant = $agence->modulesActifs();

        // un module non souscrit auprès de la plateforme ne peut pas être activé ici
        $this->service->synchroniserModules($agence, array_values(array_intersect($demandes, $this->souscrits())));
        Menu::oublierArbres();
        $apres = $agence->modulesActifs();

        Audit::logSave(['action' => 'MODIFIER MODULES', 'code_piece' => $agence->id, 'menu' => 'MODULES ACTIVES', 'etat' => 'Succès', 'objet' => 'ADMINISTRATION',
            'anciennes_valeurs' => ['modules' => $avant], 'nouvelles_valeurs' => ['modules' => $apres]]);

        return back()->with('success', 'Modules enregistrés : ' . count($apres) . ' module(s) actif(s).');
    }

    /** Modules souscrits par l'agence ; aucune souscription enregistrée = tous les modules. */
    protected function souscrits(): array
    {
        return $this->agenceCourante->agence()->modules()->pluck('module')->all() ?: array_keys(config('immobilier.modules'));
    }
}
