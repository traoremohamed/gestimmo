<?php

namespace App\Http\Middleware;

use App\Models\Agence;
use App\Support\AgenceCourante;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Détermine l'agence de la requête :
 *  - super administrateur : l'agence choisie en session (ou aucune : administration de la plateforme) ;
 *  - utilisateur d'agence : l'agence choisie parmi ses agences autorisées, sinon son agence principale.
 * Déconnecte si le compte n'est plus autorisé (désactivé, hors période d'accès, agence suspendue ou abonnement expiré).
 */
class DefinirAgenceCourante
{
    public function __construct(protected AgenceCourante $agenceCourante)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            if (!$user->accesAutorise()) {
                Auth::logout();
                $request->session()->invalidate();

                return redirect()->route('connexion')->with('error', 'Votre accès n\'est plus actif (compte, période d\'accès ou abonnement de l\'agence). Contactez l\'administrateur.');
            }

            $choisie = $request->session()->get('agence_id');
            if ($user->estSuperAdmin()) {
                $agence = $choisie ? Agence::find($choisie) : null;
            } else {
                $autorisees = $user->agencesAutorisees();
                $id = $choisie && in_array((int) $choisie, $autorisees, true) ? (int) $choisie : $user->agence_id;
                $agence = Agence::find($id);
                if (!$agence?->abonnementValide()) {
                    $request->session()->forget('agence_id');
                    $agence = $user->agence;
                }
            }

            $this->agenceCourante->definir($agence);
        }

        View::share('agenceCourante', $this->agenceCourante->agence());

        return $next($request);
    }
}
