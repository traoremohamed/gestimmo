<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lotissement extends Model
{
    use AppartientAAgence, SoftDeletes;

    protected $fillable = [
        'agence_id', 'programme_id', 'nom', 'reference', 'type_titre', 'numero_titre', 'prefecture', 'village', 'superficie',
        'latitude', 'longitude', 'description', 'actif', 'legacy_id',
    ];

    protected $casts = ['superficie' => 'decimal:2', 'actif' => 'boolean'];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
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
