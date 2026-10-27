<?php

namespace App\Services;

use App\Models\Bail;
use App\Models\Copropriete;
use App\Models\DemandeContact;
use App\Models\Echeance;
use App\Models\Loyer;
use App\Models\PromessePaiement;
use App\Models\Recouvrement;
use App\Models\RendezVous;
use App\Models\Reservation;
use App\Models\Rh\Absence;
use App\Models\Rh\PeriodePaie;
use App\Models\TiersPiece;
use App\Models\User;
use App\Support\AgenceCourante;
use Illuminate\Support\Facades\Cache;

/**
 * Alertes de l'utilisateur (cloche de la barre supérieure et page Alertes), selon ses droits
 * et les modules actifs : impayés, promesses échues, échéances proches, baux à terme, congés à valider…
 *
 * @phpstan-type Alerte array{niveau: string, icone: string, message: string, url: string, nombre: int}
 */
class Alertes
{
    public function __construct(protected AgenceCourante $agenceCourante, protected Parametres $parametres)
    {
    }

    /**
     * @return array<int, array{niveau: string, icone: string, message: string, url: string, nombre: int}>
     */
    public function pour(User $user): array
    {
        $agence = $this->agenceCourante->agence();
        if (!$agence) {
            return [];
        }

        return Cache::remember("alertes.{$agence->id}.{$user->id}", 300, fn () => $this->calculer($user));
    }

    public function oublier(User $user): void
    {
        Cache::forget('alertes.' . $this->agenceCourante->id() . '.' . $user->id);
    }

    protected function calculer(User $user): array
    {
        $actif = fn (string $module) => $this->agenceCourante->moduleActif($module);
        $jours = (int) $this->parametres->get('recouvrement.alerte_jours_avant');
        $alertes = [];
        $ajouter = function (bool $condition, string $niveau, string $icone, int $nombre, string $message, string $url) use (&$alertes) {
            if ($condition && $nombre > 0) {
                $alertes[] = compact('niveau', 'icone', 'message', 'url', 'nombre');
            }
        };

        if ($actif('recouvrement') && $user->can('recouvrements-index')) {
            $dossiers = Recouvrement::whereNotIn('statut', ['solde', 'abandonne']);
            $ajouter(true, 'danger', 'ti-alarm', (clone $dossiers)->count(),
                (clone $dossiers)->count() . ' débiteur(s) à relancer — ' . montant((clone $dossiers)->sum('montant_du')), route('recouvrements.index'));
            $ajouter(true, 'warning', 'ti-calendar-due', PromessePaiement::where('statut', 'en_attente')->whereDate('date_promesse', '<=', today())->count(),
                'promesse(s) de paiement à vérifier aujourd\'hui', route('recouvrements.index', ['statut' => 'promesse']));
            $ajouter(true, 'warning', 'ti-flag', Recouvrement::whereNotIn('statut', ['solde', 'abandonne'])->whereDate('prochaine_action', '<=', today())->count(),
                'dossier(s) de recouvrement avec une action prévue', route('recouvrements.index', ['action' => 'jour']));
        }

        if ($actif('vente') && $user->can('echeances-index')) {
            $ajouter(true, 'info', 'ti-calendar-event', Echeance::impayes()->whereBetween('date_echeance', [today(), today()->addDays($jours)])
                ->whereHas('vente', fn ($q) => $q->where('statut', 'en_cours'))->count(), "échéance(s) de vente dans les {$jours} prochains jours", route('echeances.index', ['filtre' => '30j']));
        }
        if ($actif('vente') && $user->can('reservations-index')) {
            $ajouter(true, 'warning', 'ti-lock', Reservation::where('statut', 'active')->whereBetween('date_expiration', [today(), today()->addDays(2)])->count(),
                'réservation(s) expirent sous 48 h', route('reservations.index'));
        }
        if ($actif('location') && $user->can('loyers-index')) {
            $ajouter(true, 'info', 'ti-key', Loyer::impayes()->whereBetween('date_echeance', [today(), today()->addDays($jours)])->count(),
                "loyer(s) arrivent à échéance sous {$jours} jours", route('loyers.index'));
        }
        if ($actif('location') && $user->can('baux-index')) {
            $ajouter(true, 'warning', 'ti-file-time', Bail::where('statut', 'actif')->where('tacite_reconduction', false)
                ->whereBetween('date_fin', [today(), today()->addDays(60)])->count(), 'bail(s) arrivent à terme sous 60 jours', route('baux.index', ['statut' => 'actif']));
        }
        if ($actif('syndic') && $user->can('coproprietes-index')) {
            $ajouter(true, 'warning', 'ti-building-community', Copropriete::whereBetween('mandat_fin', [today(), today()->addDays(90)])->count(),
                'mandat(s) de syndic à renouveler sous 90 jours', route('coproprietes.index'));
        }
        if ($user->can('tiers-index')) {
            $ajouter(true, 'info', 'ti-id', TiersPiece::whereDate('date_expiration', '<', today())->count(), 'pièce(s) d\'identité de tiers expirée(s)', route('tiers.index'));
        }
        if ($user->can('demandescontact-index')) {
            $ajouter(true, 'info', 'ti-mail', DemandeContact::where('statut', 'nouvelle')->count(), 'nouvelle(s) demande(s) du site web', route('demandescontact.index', ['statut' => 'nouvelle']));
        }
        if ($user->can('rendezvous-index')) {
            $ajouter(true, 'info', 'ti-calendar', RendezVous::whereDate('debut', today())->whereIn('statut', ['planifie', 'reporte'])
                ->when(!$user->can('rendezvous-tous'), fn ($q) => $q->where('user_id', $user->id))->count(), 'rendez-vous aujourd\'hui', route('rendezvous.index', ['periode' => 'jour']));
        }
        if ($actif('location') && $user->can('baux-sortie')) {
            $ajouter(true, 'warning', 'ti-receipt-refund', \App\Models\Bail::where('statut', 'resilie')->whereDoesntHave('decomptes')
                ->whereDate('date_resiliation', '>=', today()->subYear())->count(), 'bail(aux) résilié(s) sans décompte de sortie', route('baux.index', ['statut' => 'resilie']));
            $delai = (int) $this->parametres->get('sortie.delai_restitution_jours');
            $ajouter($delai > 0, 'danger', 'ti-cash-banknote', \App\Models\DecompteSortie::where('statut', 'valide')->whereDate('date_sortie', '<', today()->subDays($delai))->count(),
                'garantie(s) à restituer au-delà de ' . $delai . ' jours après la sortie', route('baux.index', ['statut' => 'resilie']));
        }
        if ($user->can('validations-index')) {
            $aDecider = \App\Services\Validation\ValidationService::aDecider($user);
            $ajouter(true, 'warning', 'ti-checks', $aDecider->count(), 'demande(s) de validation à décider', route('validations.index'));
            $ajouter(true, 'danger', 'ti-clock-exclamation', $aDecider->filter(fn ($d) => $d->echeance_etape_at?->isPast())->count(),
                'demande(s) de validation hors délai', route('validations.index'));
        }
        if ($actif('rh') && $user->can('absences-valider')) {
            $ajouter(true, 'warning', 'ti-beach', Absence::where('statut', 'demandee')->count(), 'demande(s) de congé à valider', route('absences.index', ['statut' => 'demandee']));
        }
        if ($actif('paie') && $user->can('paie-valider')) {
            $ajouter(true, 'info', 'ti-report-money', PeriodePaie::where('statut', 'calculee')->count(), 'période(s) de paie calculée(s) à valider', route('paie.index'));
        }

        return $alertes;
    }
}
