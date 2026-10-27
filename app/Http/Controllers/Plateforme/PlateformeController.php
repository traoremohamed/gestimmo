<?php

namespace App\Http\Controllers\Plateforme;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\Systeme\Sauvegarde;
use App\Services\Securite\DiagnosticSecurite;
use Illuminate\Support\Facades\DB;

/**
 * Plateforme › Tableau de bord (repris de MARIEATTIEKESAAS) : agences clientes, abonnements, activité,
 * état des sauvegardes et score de sécurité. Super administrateur uniquement.
 */
class PlateformeController extends Controller
{
    public function tableau()
    {
        $depuis = now()->subDays(30)->toDateString();
        $utilisateurs = DB::table('users')->where('est_super_admin', false)->whereNotNull('agence_id')->groupBy('agence_id')->selectRaw('agence_id, count(*) as nb')->pluck('nb', 'agence_id');
        $biens = DB::table('biens')->whereNull('deleted_at')->groupBy('agence_id')->selectRaw('agence_id, count(*) as nb')->pluck('nb', 'agence_id');
        $encaisse = DB::table('encaissements')->where('date_encaissement', '>=', $depuis)->where('statut', 'valide')
            ->groupBy('agence_id')->selectRaw('agence_id, sum(montant) as total')->pluck('total', 'agence_id');

        $agences = Agence::orderBy('raison_sociale')->get();
        $controles = DiagnosticSecurite::controles();

        return view('plateforme.tableau', [
            'agences' => $agences,
            'utilisateurs' => $utilisateurs,
            'biens' => $biens,
            'encaisse' => $encaisse,
            'stats' => [
                'agences' => $agences->count(),
                'actives' => $agences->filter(fn ($a) => $a->abonnementValide())->count(),
                'expirant' => $agences->filter(fn ($a) => $a->abonnement_fin && $a->abonnement_fin->between(today(), today()->addDays(30)))->count(),
                'vitrines' => $agences->where('vitrine_active', true)->count(),
                'utilisateurs' => $utilisateurs->sum(),
                'encaisse' => (float) $encaisse->sum(),
            ],
            'sauvegarde' => Sauvegarde::latest('id')->first(),
            'score' => DiagnosticSecurite::score($controles),
            'critiques' => collect($controles)->where('ok', false)->where('gravite', 'CRITIQUE')->values(),
        ]);
    }
}
