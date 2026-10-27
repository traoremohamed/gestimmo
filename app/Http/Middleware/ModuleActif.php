<?php

namespace App\Http\Middleware;

use App\Support\AgenceCourante;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route réservée à un module activé pour l'agence courante :
 *   Route::middleware('module:syndic')
 */
class ModuleActif
{
    public function __construct(protected AgenceCourante $agenceCourante)
    {
    }

    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (!$this->agenceCourante->existe()) {
            return redirect()->route('agences.index')->with('warning', 'Sélectionnez une agence pour accéder aux modules métier.');
        }

        if (!$this->agenceCourante->moduleActif($module)) {
            abort(403, 'Le module « ' . (config("immobilier.modules.$module.libelle") ?? $module) . ' » n\'est pas activé pour votre agence.');
        }

        return $next($request);
    }
}
