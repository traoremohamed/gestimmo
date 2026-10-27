<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier de recouvrement d'un débiteur pour une activité (vente, location, syndic, meublé).
 */
class Recouvrement extends Model
{
    use AppartientAAgence;

    public const STATUTS = [
        'amiable' => ['Amiable', 'warning'], 'promesse' => ['Promesse de paiement', 'info'], 'contentieux' => ['Contentieux', 'danger'],
        'protocole' => ['Protocole / échéancier', 'primary'], 'solde' => ['Soldé', 'success'], 'abandonne' => ['Abandonné (perte)', 'dark'],
    ];

    public const ACTIVITES = ['vente' => 'Vente', 'location' => 'Location', 'syndic' => 'Copropriété', 'meuble' => 'Meublé'];

    protected $fillable = [
        'agence_id', 'tiers_id', 'activite', 'montant_du', 'nombre_impayes', 'plus_ancienne_echeance', 'niveau', 'statut', 'gestionnaire_id',
        'prochaine_action', 'commentaire', 'solde_le',
    ];

    protected $attributes = ['statut' => 'amiable', 'niveau' => 0];

    protected $casts = ['plus_ancienne_echeance' => 'date', 'prochaine_action' => 'date', 'solde_le' => 'datetime', 'montant_du' => 'decimal:2'];

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }

    public function gestionnaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestionnaire_id');
    }

    public function relances(): HasMany
    {
        return $this->hasMany(Relance::class);
    }

    public function promesses(): HasMany
    {
        return $this->hasMany(PromessePaiement::class);
    }

    public function joursRetard(): int
    {
        return $this->plus_ancienne_echeance ? max(0, (int) $this->plus_ancienne_echeance->diffInDays(today())) : 0;
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut][0] ?? $this->statut;
    }

    public function couleurStatut(): string
    {
        return self::STATUTS[$this->statut][1] ?? 'secondary';
    }

    public function estOuvert(): bool
    {
        return !in_array($this->statut, ['solde', 'abandonne'], true);
    }
}
