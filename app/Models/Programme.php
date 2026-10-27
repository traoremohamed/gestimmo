<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Programme immobilier / site (lotissement, résidence, immeuble ou mixte).
 */
class Programme extends Model
{
    use AppartientAAgence, SoftDeletes;

    public const TYPES = ['lotissement' => 'Lotissement', 'residence' => 'Résidence', 'immeuble' => 'Immeuble', 'mixte' => 'Mixte'];

    protected $fillable = [
        'agence_id', 'code', 'nom', 'type', 'ville', 'commune', 'adresse', 'latitude', 'longitude', 'date_lancement',
        'date_livraison_prevue', 'description', 'publie', 'actif', 'legacy_id',
    ];

    protected $casts = ['date_lancement' => 'date', 'date_livraison_prevue' => 'date', 'publie' => 'boolean', 'actif' => 'boolean'];

    public function lotissements(): HasMany
    {
        return $this->hasMany(Lotissement::class);
    }

    public function immeubles(): HasMany
    {
        return $this->hasMany(Immeuble::class);
    }

    public function biens(): HasMany
    {
        return $this->hasMany(Bien::class);
    }

    public function medias(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('ordre');
    }
}
