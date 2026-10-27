<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oblige l'utilisateur à changer son mot de passe à la première connexion (flag_mdp = false).
 */
class RedirectIfFirstPasswordNotChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->flag_mdp) {
            return redirect()->route('modifier.mot.passe')
                ->with('warning', 'Attention : vous devez changer votre mot de passe avant d\'accéder à cette interface.');
        }

        return $next($request);
    }
}
