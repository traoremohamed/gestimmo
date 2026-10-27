<?php

namespace App\Models\Compta;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercice extends Model
{
    use AppartientAAgence;

    protected $table = 'cpt_exercices';

    protected $fillable = ['agence_id', 'code', 'date_debut', 'date_fin', 'statut', 'resultat', 'cloture_le', 'cloture_par'];

    protected $attributes = ['statut' => 'ouvert'];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'cloture_le' => 'datetime', 'resultat' => 'decimal:2'];

    public function ecritures(): HasMany
    {
        return $this->hasMany(Ecriture::class);
    }

    public function estOuvert(): bool
    {
        return $this->statut === 'ouvert';
    }
}
