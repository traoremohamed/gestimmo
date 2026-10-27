<?php

namespace App\Models;

use App\Enums\StatutReclamation;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reclamation extends Model
{
    use AppartientAAgence;

    public const PRIORITES = ['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'];

    protected $fillable = [
        'agence_id', 'tiers_id', 'bien_id', 'type', 'priorite', 'objet', 'message', 'statut', 'assigne_a', 'resolution', 'resolue_le',
        'created_by', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'ouverte', 'priorite' => 'normale'];

    protected $casts = ['statut' => StatutReclamation::class, 'resolue_le' => 'datetime'];

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }
}
