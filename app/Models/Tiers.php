<?php

namespace App\Models;

use App\Enums\RoleTiers;
use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Personne physique ou morale : prospect, acquéreur, locataire, propriétaire,
 * copropriétaire, fournisseur, garant (rôles cumulables, table tiers_roles).
 */
class Tiers extends Model
{
    use AppartientAAgence, SoftDeletes;

    protected $table = 'tiers';

    protected $fillable = [
        'agence_id', 'code', 'type_personne', 'civilite', 'nom', 'prenoms', 'raison_sociale', 'sexe', 'date_naissance', 'lieu_naissance',
        'nationalite_id', 'situation_matrimoniale', 'regime_matrimonial', 'profession', 'employeur', 'indicatif', 'telephone', 'telephone2',
        'email', 'email2', 'adresse', 'ville', 'boite_postale', 'pays_id', 'rccm', 'compte_contribuable', 'representant_legal', 'corps_metier',
        'conjoint', 'filiation', 'canal_acquisition', 'commercial_id', 'user_id', 'banque', 'rib', 'notes', 'created_by', 'legacy_id',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'conjoint' => 'array',
        'filiation' => 'array',
    ];

    protected ?array $rolesCache = null;

    public function getNomCompletAttribute(): string
    {
        if ($this->type_personne === 'morale') {
            return $this->raison_sociale ?: $this->nom;
        }

        return trim($this->nom . ' ' . $this->prenoms);
    }

    public function getContactAttribute(): string
    {
        $telephone = $this->telephone ? trim(($this->indicatif ? '+' . $this->indicatif . ' ' : '') . $this->telephone) : null;

        return collect([$telephone, $this->email])->filter()->implode(' · ');
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return $this->rolesCache ??= DB::table('tiers_roles')->where('tiers_id', $this->id)->pluck('role')->all();
    }

    public function aRole(RoleTiers|string $role): bool
    {
        return in_array($role instanceof RoleTiers ? $role->value : $role, $this->roles(), true);
    }

    public function ajouterRole(RoleTiers|string ...$roles): void
    {
        foreach ($roles as $role) {
            DB::table('tiers_roles')->insertOrIgnore([
                'tiers_id' => $this->id,
                'role' => $role instanceof RoleTiers ? $role->value : $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->rolesCache = null;
    }

    /**
     * @param  string[]  $roles
     */
    public function synchroniserRoles(array $roles): void
    {
        DB::table('tiers_roles')->where('tiers_id', $this->id)->whereNotIn('role', $roles)->delete();
        $this->ajouterRole(...$roles);
    }

    public function scopeAvecRole(Builder $query, RoleTiers|string ...$roles): Builder
    {
        $valeurs = array_map(fn ($r) => $r instanceof RoleTiers ? $r->value : $r, $roles);

        return $query->whereIn($this->qualifyColumn('id'), DB::table('tiers_roles')->select('tiers_id')->whereIn('role', $valeurs));
    }

    public function scopeRecherche(Builder $query, ?string $terme): Builder
    {
        if (!$terme) {
            return $query;
        }

        $terme = '%' . mb_strtolower($terme) . '%';

        return $query->where(fn ($q) => $q->whereRaw('lower(nom) like ?', [$terme])
            ->orWhereRaw('lower(prenoms) like ?', [$terme])
            ->orWhereRaw('lower(raison_sociale) like ?', [$terme])
            ->orWhere('telephone', 'like', $terme)
            ->orWhereRaw('lower(email) like ?', [$terme])
            ->orWhereRaw('lower(code) like ?', [$terme]));
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(TiersPiece::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nationalite(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'nationalite_id');
    }

    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class);
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class, 'acquereur_id');
    }

    public function baux(): HasMany
    {
        return $this->hasMany(Bail::class, 'locataire_id');
    }

    public function biensEnGestion(): HasMany
    {
        return $this->hasMany(Bien::class, 'proprietaire_id');
    }

    public function mandats(): HasMany
    {
        return $this->hasMany(Mandat::class, 'proprietaire_id');
    }

    public function lotsCopropriete(): HasMany
    {
        return $this->hasMany(CoproLot::class, 'coproprietaire_id');
    }

    public function encaissements(): HasMany
    {
        return $this->hasMany(Encaissement::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }

    public function sejours(): HasMany
    {
        return $this->hasMany(Sejour::class);
    }
}
