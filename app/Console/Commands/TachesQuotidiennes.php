<?php

namespace App\Console\Commands;

use App\Models\Agence;
use App\Services\Location\BailService;
use App\Services\Vente\ReservationService;
use App\Support\AgenceCourante;
use Illuminate\Console\Command;

/**
 * Tâches automatiques, agence par agence (planifiées chaque jour dans routes/console.php) :
 * expiration des réservations, génération des loyers du mois, pénalités de retard.
 */
class TachesQuotidiennes extends Command
{
    protected $signature = 'immobilier:taches-quotidiennes {--agence= : code d\'une seule agence}';

    protected $description = 'Réservations, loyers, pénalités, recouvrement (dossiers, promesses, relances), rappels d\'échéance et de rendez-vous, acquisition des congés';

    public function handle(AgenceCourante $agenceCourante, ReservationService $reservations, BailService $baux): int
    {
        $agences = Agence::where('actif', true)->when($this->option('agence'), fn ($q, $code) => $q->where('code', strtoupper($code)))->get();

        foreach ($agences->filter->abonnementValide() as $agence) {
            $agenceCourante->pour($agence, function (Agence $agence) use ($reservations, $baux) {
                $modules = $agence->modulesActifs();
                $resultat = [];

                if (in_array('vente', $modules, true)) {
                    $resultat[] = $reservations->expirer() . ' réservation(s) expirée(s)';
                }
                if (in_array('location', $modules, true)) {
                    $resultat[] = $baux->genererPourTous(today()->endOfMonth()) . ' loyer(s) généré(s)';
                    $resultat[] = $baux->appliquerPenalites() . ' pénalité(s)';
                }
                if (in_array('recouvrement', $modules, true)) {
                    $recouvrement = app(\App\Services\Recouvrement\RecouvrementService::class);
                    $resultat[] = $recouvrement->analyser()['ouverts'] . ' dossier(s) de recouvrement';
                    $resultat[] = $recouvrement->verifierPromesses() . ' promesse(s) vérifiée(s)';
                    $resultat[] = $recouvrement->relancesAutomatiques() . ' relance(s) envoyée(s)';
                }
                $rappels = app(\App\Services\Integration\RappelService::class);
                $resultat[] = $rappels->echeances() . ' rappel(s) d\'échéance';
                $resultat[] = $rappels->rendezVous() . ' rappel(s) de rendez-vous';
                if (in_array('rh', $modules, true) && today()->day === 1) {
                    $resultat[] = app(\App\Services\Rh\RhService::class)->acquerirConges() . ' acquisition(s) de congés';
                }

                $this->line($agence->code . ' : ' . (implode(', ', $resultat) ?: 'rien à faire'));
            });
        }

        return self::SUCCESS;
    }
}
