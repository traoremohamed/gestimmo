<?php

namespace App\Http\Middleware;

use App\Models\Agence;
use App\Support\AgenceCourante;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Site vitrine public : l'agence est résolue par le slug de l'URL (/site/{slug})
 * ou par son domaine personnalisé. Le site doit être activé et le module souscrit.
 */
class ResoudreAgenceVitrine
{
    public function __construct(protected AgenceCourante $agenceCourante)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        $agence = $slug
            ? Agence::where('slug', $slug)->first()
            : Agence::where('domaine', $request->getHost())->first();

        abort_unless($agence && $agence->vitrine_active && $agence->abonnementValide() && in_array('vitrine', $agence->modulesActifs(), true), 404);

        $this->agenceCourante->definir($agence);
        View::share('agenceCourante', $agence);
        View::share('slug', $agence->slug);

        // réglages et pages du site paramétrable (modèle de départ créé à la première visite)
        $site = \App\Services\Site\SiteService::pour($agence);
        if (\App\Models\Site\Page::where('site_id', $site->id)->doesntExist()) {
            \App\Services\Site\SiteService::creerModele($site);
        }
        View::share('siteVitrine', $site);
        View::share('pagesVitrine', \App\Models\Site\Page::where('site_id', $site->id)->where('publie', true)->where('dans_menu', true)
            ->orderByDesc('est_accueil')->orderBy('ordre')->get());

        // les routes nommées de la vitrine reçoivent le slug automatiquement
        \Illuminate\Support\Facades\URL::defaults(['slug' => $agence->slug]);

        return $next($request);
    }
}
