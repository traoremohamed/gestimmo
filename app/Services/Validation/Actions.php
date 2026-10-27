<?php

namespace App\Services\Validation;

use App\Helpers\Menu;
use App\Models\Role;
use App\Models\Rh\Employe;
use App\Models\Validation\Demande;
use App\Services\Finance\EncaissementService;
use App\Services\Location\BailService;
use App\Services\Location\ReversementService;
use App\Services\Location\SortieService;
use App\Services\Parametres;
use App\Services\Rh\PaieService;
use App\Services\Rh\RhService;
use App\Services\Syndic\SyndicService;
use App\Services\Vente\VenteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Exécution des actions approuvées par le moteur de validation : une méthode par code du catalogue
 * (BAIL_ACTIVATION → bailActivation). Elles appellent les mêmes services métier que l'écran d'origine, avec les
 * données saisies au moment de la demande. Le suffixe « Rejet » traite un refus (ou un retrait de la demande).
 */
class Actions
{
    public function venteValidation(?Model $vente, array $d, Demande $demande): void
    {
        app(VenteService::class)->valider($this->objet($vente), $demande->demandeur);
    }

    public function venteDesistement(?Model $vente, array $d, Demande $demande): void
    {
        app(VenteService::class)->desister($this->objet($vente), $d['motif'] ?? 'AUTRE', $d['observation'] ?? null, $d['taux_retenue'] ?? null);
    }

    public function bailActivation(?Model $bail, array $d, Demande $demande): void
    {
        app(BailService::class)->activer($this->objet($bail));
    }

    public function bailResiliation(?Model $bail, array $d, Demande $demande): void
    {
        app(BailService::class)->resilier($this->objet($bail), Carbon::parse($d['date_resiliation']), $d['motif']);
    }

    public function decompteSortie(?Model $decompte, array $d, Demande $demande): void
    {
        app(SortieService::class)->valider($this->objet($decompte), $demande->demandeur);
    }

    public function restitutionGarantie(?Model $decompte, array $d, Demande $demande): void
    {
        app(SortieService::class)->restituer($this->objet($decompte), $d, null, $demande->demandeur);
    }

    public function reversementPaiement(?Model $reversement, array $d, Demande $demande): void
    {
        app(ReversementService::class)->payer($this->objet($reversement), $d['mode_paiement'], $d['reference'] ?? null);
    }

    public function appelFondsEmission(?Model $appel, array $d, Demande $demande): void
    {
        app(SyndicService::class)->emettre($this->objet($appel));
    }

    public function encaissementAnnulation(?Model $encaissement, array $d, Demande $demande): void
    {
        app(EncaissementService::class)->annuler($this->objet($encaissement), $d['motif'] ?? (string) $demande->motif, $demande->demandeur);
    }

    public function depensePaiement(?Model $depense, array $d, Demande $demande): void
    {
        $this->objet($depense)->update(['statut' => 'payee']);   // la comptabilisation suit l'enregistrement de la dépense
    }

    public function depensePaiementRejet(?Model $depense, string $commentaire, Demande $demande): void
    {
        $depense?->update(['statut' => 'annulee']);
    }

    public function absenceApprobation(?Model $absence, array $d, Demande $demande): void
    {
        app(RhService::class)->decider($this->objet($absence), true, $d['commentaire'] ?? 'Approuvée par le circuit ' . $demande->numero);
    }

    public function absenceApprobationRejet(?Model $absence, string $commentaire, Demande $demande): void
    {
        if ($absence && $absence->statut === 'demandee') {
            app(RhService::class)->decider($absence, false, $commentaire);
        }
    }

    public function avanceSalaire(?Model $employe, array $d, Demande $demande): void
    {
        app(RhService::class)->accorderAvance($employe instanceof Employe ? $employe : Employe::findOrFail($d['employe_id']), $d);
    }

    public function paieValidation(?Model $periode, array $d, Demande $demande): void
    {
        app(PaieService::class)->valider($this->objet($periode));
    }

    public function utilisateurProfil(?Model $user, array $d, Demande $demande): void
    {
        $role = Role::visiblesPour($demande->demandeur)->findOrFail($d['role_id']);
        $this->objet($user)->syncRoles([$role]);
        Menu::oublierArbres();
    }

    public function parametresModification(?Model $objet, array $d, Demande $demande): void
    {
        $parametres = app(Parametres::class);
        foreach ((array) ($d['valeurs'] ?? []) as $cle => $valeur) {
            if (array_key_exists($cle, config('immobilier.parametres'))) {
                $parametres->definir($cle, $valeur);
            }
        }
    }

    protected function objet(?Model $objet): Model
    {
        return $objet ?? throw new RuntimeException('L\'objet de la demande n\'existe plus.');
    }
}
