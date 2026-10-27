<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use App\Support\Montant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Frais annexe paramétrable (ouverture de dossier, ACD, notaire, contrat de bail…).
 */
class FraisType extends Model
{
    use AppartientAAgence;

    public const ACTIVITES = ['vente' => 'Vente', 'location' => 'Location', 'syndic' => 'Syndic', 'meuble' => 'Meublé'];

    protected $fillable = ['agence_id', 'code', 'libelle', 'activite', 'mode_calcul', 'montant', 'obligatoire', 'document_requis', 'actif'];

    protected $casts = ['montant' => 'decimal:2', 'obligatoire' => 'boolean', 'document_requis' => 'boolean', 'actif' => 'boolean'];

    public function scopeActivite(Builder $query, string $activite): Builder
    {
        return $query->where('activite', $activite)->where('actif', true)->orderByDesc('obligatoire')->orderBy('libelle');
    }

    /**
     * Montant du frais pour une base (prix du bien, loyer) quand il est en pourcentage.
     */
    public function montantPour($base): string
    {
        return $this->mode_calcul === 'pourcentage' ? Montant::pourcentage($base, $this->montant) : Montant::normaliser($this->montant);
    }
}
