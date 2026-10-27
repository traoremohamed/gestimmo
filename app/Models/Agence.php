<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Agence immobilière cliente de la plateforme (locataire SaaS).
 */
class Agence extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'raison_sociale', 'sigle', 'slug', 'domaine', 'email', 'telephone', 'telephone2', 'adresse', 'ville',
        'pays_id', 'rccm', 'compte_contribuable', 'logo', 'couleur', 'devise', 'plan', 'abonnement_fin', 'vitrine_active', 'actif',
    ];

    protected $casts = [
        'abonnement_fin' => 'date',
        'vitrine_active' => 'boolean',
        'actif' => 'boolean',
    ];

    protected ?array $modulesActifsCache = null;

    public function modules(): HasMany
    {
        return $this->hasMany(AgenceModule::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class);
    }

    /**
     * Codes des modules actifs (les modules obligatoires le sont toujours).
     *
     * @return string[]
     */
    public function modulesActifs(): array
    {
        if ($this->modulesActifsCache === null) {
            $obligatoires = array_keys(array_filter(config('immobilier.modules'), fn ($m) => $m['obligatoire'] ?? false));
            $actives = $this->modules()->where('actif', true)->pluck('module')->all();
            $this->modulesActifsCache = array_values(array_unique([...$obligatoires, ...$actives]));
        }

        return $this->modulesActifsCache;
    }

    public function oublierModules(): void
    {
        $this->modulesActifsCache = null;
    }

    public function abonnementValide(): bool
    {
        return $this->actif && ($this->abonnement_fin === null || $this->abonnement_fin->endOfDay()->isFuture());
    }

    public function getNomAttribute(): string
    {
        return $this->sigle ?: $this->raison_sociale;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}
