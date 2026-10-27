<?php

namespace App\Support;

use App\Models\Agence;

/**
 * Agence active pour la requête en cours (singleton).
 *
 * - utilisateur d'agence : son agence_id ;
 * - utilisateur plateforme (agence_id null) : agence choisie, mémorisée en session ;
 * - site vitrine : agence résolue depuis le slug / le domaine ;
 * - console, import : aucune agence (pas de filtre) sauf si on en définit une.
 */
class AgenceCourante
{
    protected ?Agence $agence = null;

    public function definir(?Agence $agence): void
    {
        $this->agence = $agence;
    }

    public function agence(): ?Agence
    {
        return $this->agence;
    }

    public function id(): ?int
    {
        return $this->agence?->id;
    }

    public function existe(): bool
    {
        return $this->agence !== null;
    }

    /**
     * Exécute un traitement dans le contexte d'une agence (commandes, jobs, tests).
     */
    public function pour(Agence $agence, callable $callback): mixed
    {
        $precedente = $this->agence;
        $this->agence = $agence;

        try {
            return $callback($agence);
        } finally {
            $this->agence = $precedente;
        }
    }

    public function moduleActif(string $module): bool
    {
        if (!$this->agence) {
            return false;
        }

        return in_array($module, $this->agence->modulesActifs(), true);
    }
}
