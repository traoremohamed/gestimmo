<?php

namespace App\Models;

use App\Enums\StatutRendezVous;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RendezVous extends Model
{
    use AppartientAAgence;

    protected $table = 'rendez_vous';

    protected $fillable = [
        'agence_id', 'tiers_id', 'prospect_id', 'bien_id', 'programme_id', 'user_id', 'objet', 'debut', 'fin', 'lieu', 'statut',
        'reporte_au', 'commentaire', 'compte_rendu', 'source', 'contact_nom', 'contact_telephone', 'contact_email', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'planifie', 'objet' => 'VISITE', 'source' => 'backoffice'];

    protected $casts = [
        'statut' => StatutRendezVous::class,
        'debut' => 'datetime',
        'fin' => 'datetime',
        'reporte_au' => 'datetime',
    ];

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getInterlocuteurAttribute(): string
    {
        return $this->tiers?->nom_complet ?? (string) $this->contact_nom;
    }
}
