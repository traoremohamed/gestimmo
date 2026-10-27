<?php

namespace App\Http\Controllers;

use App\Services\Validation\ValidationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Workflow de validation : si un circuit actif couvre l'action, elle est mise en attente (demande créée,
     * validateurs prévenus) et la réponse l'annonce ; sinon null et l'action s'exécute normalement.
     */
    protected function soumettre(string $action, ?Model $objet, array $donnees = [], ?string $motif = null, ?string $filtre = null, ?float $montant = null): ?RedirectResponse
    {
        $demande = ValidationService::intercepter($action, $objet, $donnees, $motif, $montant, $filtre);

        return $demande
            ? back()->with('info', 'Demande ' . $demande->numero . ' soumise à validation (' . $demande->circuit->libelle . ') : l\'action sera exécutée après approbation.')
            : null;
    }
}
