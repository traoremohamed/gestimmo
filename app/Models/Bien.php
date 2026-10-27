<?php

namespace App\Models;

use App\Enums\StatutBail;
use App\Enums\StatutBien;
use App\Enums\StatutSejour;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bien immobilier : terrain, villa, appartement, bureau, local, parking…
 * Un même bien peut être à vendre, à louer et/ou proposé en meublé.
 */
class Bien extends Model
{
    use AppartientAAgence, SoftDeletes;

    protected $fillable = [
        'agence_id', 'reference', 'libelle', 'type_bien', 'usage', 'programme_id', 'lotissement_id', 'immeuble_id', 'proprietaire_id',
        'mandat_id', 'ilot', 'numero_lot', 'etage', 'porte', 'superficie', 'unite_superficie', 'nb_pieces', 'nb_chambres', 'nb_salons',
        'nb_salles_bain', 'nb_toilettes', 'nb_cuisines', 'equipements', 'caracteristiques', 'a_vendre', 'a_louer', 'meuble', 'prix_vente',
        'loyer_mensuel', 'charges_mensuelles', 'prix_nuitee', 'delai_livraison_mois', 'statut', 'latitude', 'longitude', 'description',
        'publie', 'created_by', 'legacy_id',
    ];

    protected $attributes = ['statut' => 'disponible'];

    protected $casts = [
        'statut' => StatutBien::class,
        'equipements' => 'array',
        'caracteristiques' => 'array',
        'a_vendre' => 'boolean',
        'a_louer' => 'boolean',
        'meuble' => 'boolean',
        'publie' => 'boolean',
        'superficie' => 'decimal:2',
        'prix_vente' => 'decimal:2',
        'loyer_mensuel' => 'decimal:2',
        'charges_mensuelles' => 'decimal:2',
        'prix_nuitee' => 'decimal:2',
    ];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function lotissement(): BelongsTo
    {
        return $this->belongsTo(Lotissement::class);
    }

    public function immeuble(): BelongsTo
    {
        return $this->belongsTo(Immeuble::class);
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Tiers::class, 'proprietaire_id');
    }

    public function mandat(): BelongsTo
    {
        return $this->belongsTo(Mandat::class);
    }

    public function medias(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('ordre');
    }

    public function photos(): MorphMany
    {
        return $this->medias()->where('collection', 'photos');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function venteBiens(): HasMany
    {
        return $this->hasMany(VenteBien::class);
    }

    public function baux(): HasMany
    {
        return $this->hasMany(Bail::class);
    }

    public function bailActif(): HasOne
    {
        return $this->hasOne(Bail::class)->where('statut', StatutBail::Actif->value);
    }

    public function sejours(): HasMany
    {
        return $this->hasMany(Sejour::class);
    }

    public function getDesignationAttribute(): string
    {
        $localisation = collect([
            $this->ilot ? 'Îlot ' . $this->ilot : null,
            $this->numero_lot ? 'Lot ' . $this->numero_lot : null,
            $this->etage !== null && $this->etage !== '' ? 'Étage ' . $this->etage : null,
            $this->porte ? 'Porte ' . $this->porte : null,
        ])->filter()->implode(', ');

        return trim($this->reference . ' — ' . $this->libelle . ($localisation ? " ($localisation)" : ''));
    }

    public function estDisponible(): bool
    {
        return $this->statut === StatutBien::Disponible;
    }

    /**
     * Le bien est-il libre pour un séjour sur la période [arrivée, départ[ ?
     */
    public function estLibrePourSejour($arrivee, $depart, ?int $saufSejourId = null): bool
    {
        return !$this->sejours()
            ->whereNotIn('statut', [StatutSejour::Annule->value, StatutSejour::Termine->value])
            ->when($saufSejourId, fn ($q) => $q->whereKeyNot($saufSejourId))
            ->where('date_arrivee', '<', $depart)
            ->where('date_depart', '>', $arrivee)
            ->exists();
    }

    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('statut', StatutBien::Disponible->value);
    }

    public function scopeFiltrer(Builder $query, array $filtres): Builder
    {
        return $query
            ->when($filtres['q'] ?? null, fn ($q, $t) => $q->where(fn ($s) => $s->whereRaw('lower(reference) like ?', ['%' . mb_strtolower($t) . '%'])
                ->orWhereRaw('lower(libelle) like ?', ['%' . mb_strtolower($t) . '%'])))
            ->when($filtres['type_bien'] ?? null, fn ($q, $v) => $q->where('type_bien', $v))
            ->when($filtres['statut'] ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->when($filtres['programme_id'] ?? null, fn ($q, $v) => $q->where('programme_id', $v))
            ->when($filtres['lotissement_id'] ?? null, fn ($q, $v) => $q->where('lotissement_id', $v))
            ->when($filtres['immeuble_id'] ?? null, fn ($q, $v) => $q->where('immeuble_id', $v))
            ->when(($filtres['destination'] ?? null) === 'vente', fn ($q) => $q->where('a_vendre', true))
            ->when(($filtres['destination'] ?? null) === 'location', fn ($q) => $q->where('a_louer', true))
            ->when(($filtres['destination'] ?? null) === 'meuble', fn ($q) => $q->where('meuble', true));
    }
}
