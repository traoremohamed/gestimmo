<?php

namespace App\Services;

use App\Models\Parametre;
use App\Support\AgenceCourante;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres métier de l'agence courante. La valeur par défaut vient de
 * config('immobilier.parametres'), la table parametres la surcharge.
 *
 *   app(Parametres::class)->get('vente.taux_acompte')  // 30
 *   parametre('vente.taux_acompte')
 */
class Parametres
{
    public function __construct(protected AgenceCourante $agenceCourante)
    {
    }

    public function get(string $cle, mixed $defaut = null): mixed
    {
        // les clés contiennent un point (vente.taux_acompte) : pas de notation pointée ici
        $definition = config('immobilier.parametres')[$cle] ?? null;
        $valeurs = $this->valeursAgence();

        $brute = array_key_exists($cle, $valeurs) ? $valeurs[$cle] : ($definition[2] ?? $defaut);

        return $this->typer($brute, $definition[1] ?? 'texte');
    }

    public function definir(string $cle, mixed $valeur): void
    {
        $agenceId = $this->agenceCourante->id() ?? throw new \LogicException('Aucune agence courante');

        if (is_bool($valeur)) {
            $valeur = $valeur ? '1' : '0';
        }

        Parametre::updateOrCreate(['agence_id' => $agenceId, 'cle' => $cle], ['valeur' => $valeur]);
        Cache::forget($this->cleCache($agenceId));
    }

    /**
     * Paramètres groupés pour l'écran de paramétrage.
     *
     * @return array<string, array<string, array{libelle: string, type: string, valeur: mixed}>>
     */
    public function parGroupe(): array
    {
        $groupes = [];
        foreach (config('immobilier.parametres') as $cle => $definition) {
            [$libelle, $type, , $groupe] = $definition;
            $groupes[$groupe][$cle] = ['libelle' => $libelle, 'type' => $type, 'valeur' => $this->get($cle), 'options' => $definition[4] ?? []];
        }

        return $groupes;
    }

    protected function valeursAgence(): array
    {
        $agenceId = $this->agenceCourante->id();
        if ($agenceId === null) {
            return [];
        }

        return Cache::remember($this->cleCache($agenceId), 3600, fn () => Parametre::where('agence_id', $agenceId)->pluck('valeur', 'cle')->all());
    }

    protected function typer(mixed $valeur, string $type): mixed
    {
        return match ($type) {
            'entier' => (int) $valeur,
            'decimal', 'pourcentage' => (float) $valeur,
            'booleen' => filter_var($valeur, FILTER_VALIDATE_BOOLEAN),
            default => $valeur === null ? null : (string) $valeur,
        };
    }

    protected function cleCache(int $agenceId): string
    {
        return "parametres.agence.$agenceId";
    }
}
