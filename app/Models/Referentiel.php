<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Liste de valeurs paramétrable par agence (types de bien, modes de paiement…).
 * Catégories déclarées dans config('immobilier.referentiels').
 */
class Referentiel extends Model
{
    use AppartientAAgence;

    protected $fillable = ['agence_id', 'categorie', 'code', 'libelle', 'ordre', 'actif', 'meta'];

    protected $casts = ['actif' => 'boolean', 'meta' => 'array'];

    protected static array $memo = [];

    public function scopeCategorie(Builder $query, string $categorie): Builder
    {
        return $query->where('categorie', $categorie)->orderBy('ordre')->orderBy('libelle');
    }

    /**
     * code => libellé des valeurs actives d'une catégorie (mémorisé pour la requête).
     *
     * @return Collection<string, string>
     */
    public static function options(string $categorie): Collection
    {
        $cle = (app(\App\Support\AgenceCourante::class)->id() ?? 0) . ".$categorie";

        return static::$memo[$cle] ??= static::categorie($categorie)->where('actif', true)->pluck('libelle', 'code');
    }

    public static function libelle(string $categorie, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return static::options($categorie)[$code] ?? $code;
    }

    public static function oublier(): void
    {
        static::$memo = [];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::oublier());
        static::deleted(fn () => static::oublier());
    }
}
