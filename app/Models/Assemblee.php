<?php

namespace App\Models;

use App\Enums\StatutAssemblee;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assemblee extends Model
{
    use AppartientAAgence;

    public const TYPES = ['ordinaire' => 'Ordinaire', 'extraordinaire' => 'Extraordinaire'];

    protected $fillable = [
        'agence_id', 'copropriete_id', 'type', 'date_heure', 'lieu', 'ordre_du_jour', 'statut', 'tantiemes_presents', 'proces_verbal', 'convoquee_le',
    ];

    protected $attributes = ['statut' => 'planifiee', 'type' => 'ordinaire'];

    protected $casts = [
        'statut' => StatutAssemblee::class,
        'date_heure' => 'datetime',
        'convoquee_le' => 'datetime',
        'tantiemes_presents' => 'decimal:4',
    ];

    public function copropriete(): BelongsTo
    {
        return $this->belongsTo(Copropriete::class);
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class)->orderBy('numero');
    }
}
