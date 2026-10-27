<?php

namespace App\Services\Validation;

use App\Models\AppelFonds;
use App\Models\Bail;
use App\Models\DecompteSortie;
use App\Models\Depense;
use App\Models\Encaissement;
use App\Models\Reversement;
use App\Models\Rh\Absence;
use App\Models\Rh\Employe;
use App\Models\Rh\PeriodePaie;
use App\Models\User;
use App\Models\Vente;

/**
 * Catalogue des actions soumises au moteur de validation (repris de MARIEATTIEKESAAS, actions immobilières).
 *
 * Chaque action est branchée là où elle se produit : si un circuit ACTIF existe pour elle dans l'agence
 * (Paramétrage › Circuits de validation), avec le cas échéant un montant de déclenchement, l'action est mise en attente
 * puis exécutée automatiquement après la dernière approbation (Actions::<code en camelCase>). Sans circuit actif,
 * l'action s'exécute immédiatement.
 *
 *   code => [libellé, module, classe de l'objet, attribut montant, aide sur le filtre]
 */
class Catalogue
{
    public const ACTIONS = [
        // VENTES
        'VENTE_VALIDATION' => ['Validation d\'un contrat de vente', 'vente', Vente::class, 'montant_net', null],
        'VENTE_DESISTEMENT' => ['Désistement d\'un acquéreur', 'vente', Vente::class, 'totalPaye', 'Motif de désistement'],
        // GESTION LOCATIVE
        'BAIL_ACTIVATION' => ['Activation d\'un bail', 'location', Bail::class, 'loyer_mensuel', 'Type de bail (HABITATION, COMMERCIAL…)'],
        'BAIL_RESILIATION' => ['Résiliation d\'un bail', 'location', Bail::class, 'loyer_mensuel', 'Motif de résiliation'],
        'DECOMPTE_SORTIE' => ['Validation d\'un décompte de sortie', 'location', DecompteSortie::class, 'total_retenues', null],
        'RESTITUTION_GARANTIE' => ['Restitution des garanties au locataire sortant', 'location', DecompteSortie::class, 'montant_a_rembourser', null],
        'REVERSEMENT_PAIEMENT' => ['Paiement d\'un reversement au propriétaire', 'location', Reversement::class, 'montant_net', null],
        // SYNDIC
        'APPEL_FONDS_EMISSION' => ['Émission d\'un appel de fonds', 'syndic', AppelFonds::class, 'montant_total', 'Type d\'appel (budget, travaux…)'],
        // FINANCES
        'ENCAISSEMENT_ANNULATION' => ['Annulation d\'un encaissement (reçu)', 'finance', Encaissement::class, 'montant', null],
        'DEPENSE_PAIEMENT' => ['Paiement d\'une dépense', 'finance', Depense::class, 'montant', 'Catégorie de dépense (TRAVAUX, ENTRETIEN…)'],
        // RESSOURCES HUMAINES
        'ABSENCE_APPROBATION' => ['Approbation d\'un congé / d\'une absence', 'rh', Absence::class, 'nombre_jours', 'Type d\'absence (CONGE_ANNUEL…)'],
        'AVANCE_SALAIRE' => ['Octroi d\'une avance ou d\'un prêt', 'rh', Employe::class, null, null],
        'PAIE_VALIDATION' => ['Validation d\'une période de paie', 'paie', PeriodePaie::class, 'total_net', null],
        // ADMINISTRATION
        'UTILISATEUR_PROFIL' => ['Attribution ou changement de profil d\'un utilisateur', 'admin', User::class, null, 'Nom du profil attribué'],
        'PARAMETRES_MODIFICATION' => ['Modification des paramètres métier', 'admin', null, null, 'Groupe de paramètres (Ventes, Gestion locative…) — vide = tous'],
    ];

    public static function existe(string $code): bool
    {
        return isset(self::ACTIONS[$code]);
    }

    public static function libelle(string $code): string
    {
        return self::ACTIONS[$code][0] ?? $code;
    }

    /** @return array<string, string> actions proposées (modules actifs de l'agence) */
    public static function options(): array
    {
        $agence = app(\App\Support\AgenceCourante::class);
        $options = [];
        foreach (self::ACTIONS as $code => [$libelle, $module]) {
            if ($module === 'admin' || $agence->moduleActif($module)) {
                $options[$code] = (config("immobilier.modules.$module.libelle") ?? 'Administration') . ' — ' . $libelle;
            }
        }

        return $options;
    }
}
