<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Écrans de la plateforme (structure des menus, permissions, paramètres système) : super administrateur uniquement.
 */
class ExigerSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->estSuperAdmin(), 403, 'Page réservée à l\'administration de la plateforme.');

        return $next($request);
    }
}
