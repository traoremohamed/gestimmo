<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Immeuble extends Model
{
    use AppartientAAgence, SoftDeletes;

    protected $fillable = [
        'agence_id', 'programme_id', 'lotissement_id', 'proprietaire_id', 'nom', 'ilot', 'lot', 'adresse', 'nombre_etages',
        'superficie', 'annee_construction', 'latitude', 'longitude', 'description', 'actif', 'legacy_id',
    ];

    protected $casts = ['superficie' => 'decimal:2', 'actif' => 'boolean'];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function lotissement(): BelongsTo
    {
        return $this->belongsTo(Lotissement::class);
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'proprietaire_id');
    }

    public function biens(): HasMany
    {
        return $this->hasMany(Bien::class);
    }

    public function copropriete(): HasOne
    {
        return $this->hasOne(Copropriete::class);
    }

    public function medias(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('ordre');
    }
}
