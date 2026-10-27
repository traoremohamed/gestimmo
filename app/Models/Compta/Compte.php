<?php

namespace App\Models\Compta;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compte extends Model
{
    use AppartientAAgence;

    protected $table = 'cpt_comptes';

    public const TYPES = ['CAPITAUX' => 'Capitaux', 'IMMOBILISATION' => 'Immobilisation', 'STOCK' => 'Stock', 'TIERS' => 'Tiers',
        'TRESORERIE' => 'Trésorerie', 'CHARGE' => 'Charge', 'PRODUIT' => 'Produit'];

    protected $fillable = ['agence_id', 'numero', 'libelle', 'classe', 'type', 'lettrable', 'actif'];

    protected $casts = ['lettrable' => 'boolean', 'actif' => 'boolean'];

    public function lignes(): HasMany
    {
        return $this->hasMany(Ligne::class);
    }

    public function getIntituleAttribute(): string
    {
        return $this->numero . ' — ' . $this->libelle;
    }
}
