<?php

namespace App\Services\Integration;

use App\Enums\StatutRendezVous;
use App\Models\Echeance;
use App\Models\Loyer;
use App\Models\RendezVous;
use App\Models\Tiers;
use App\Services\Parametres;
use App\Support\Montant;

/**
 * Rappels automatiques de la tâche quotidienne (modèles RAPPEL_ECHEANCE et RAPPEL_RDV) :
 * échéances de vente et loyers à régler dans N jours (paramètre notification.rappel_jours), rendez-vous du lendemain.
 * Une échéance n'est rappelée qu'une fois : le jour où elle arrive à N jours.
 */
class RappelService
{
    public function __construct(protected Parametres $parametres)
    {
    }

    public function echeances(): int
    {
        $jours = (int) $this->parametres->get('notification.rappel_jours');
        if ($jours <= 0) {
            return 0;
        }
        $date = today()->addDays($jours);
        $envoyes = 0;

        foreach (Echeance::impayes()->whereDate('date_echeance', $date)->with('vente.acquereur')->get() as $echeance) {
            $envoyes += $this->rappeler($echeance->vente?->acquereur, $echeance->libelle, $echeance->resteAPayer(), $date, $echeance);
        }
        foreach (Loyer::impayes()->whereDate('date_echeance', $date)->with('bail.locataire')->get() as $loyer) {
            $envoyes += $this->rappeler($loyer->bail?->locataire, $loyer->libelle, $loyer->resteAPayer(), $date, $loyer);
        }

        return $envoyes;
    }

    public function rendezVous(): int
    {
        $envoyes = 0;
        $rdvs = RendezVous::whereDate('debut', today()->addDay())->whereIn('statut', [StatutRendezVous::Planifie, StatutRendezVous::Reporte])->with('tiers')->get();

        foreach ($rdvs as $rdv) {
            $destinataire = $rdv->tiers
                ? ['email' => $rdv->tiers->email, 'telephone' => ($rdv->tiers->indicatif ?: '') . $rdv->tiers->telephone, 'nom' => $rdv->tiers->nom_complet]
                : ['email' => $rdv->contact_email, 'telephone' => $rdv->contact_telephone, 'nom' => $rdv->contact_nom];
            $resultat = NotificationService::notifier('RAPPEL_RDV', $destinataire, [
                'objet' => $rdv->objet, 'date' => $rdv->debut->format('d/m/Y'), 'heure' => $rdv->debut->format('H:i'), 'lieu' => $rdv->lieu ? ' (' . $rdv->lieu . ')' : '',
            ], $rdv->tiers);
            $envoyes += (int) in_array('EN_FILE', $resultat, true);
        }

        return $envoyes;
    }

    protected function rappeler(?Tiers $tiers, string $libelle, string $montant, $date, $objet): int
    {
        if (!$tiers) {
            return 0;
        }
        $resultat = NotificationService::notifier('RAPPEL_ECHEANCE', ['email' => $tiers->email, 'telephone' => ($tiers->indicatif ?: '') . $tiers->telephone, 'nom' => $tiers->nom_complet],
            ['libelle' => $libelle, 'montant' => Montant::formater($montant), 'date' => $date->format('d/m/Y')], $objet);

        return (int) in_array('EN_FILE', $resultat, true);
    }
}
