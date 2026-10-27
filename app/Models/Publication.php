<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Actualité, article ou page du site vitrine.
 */
class Publication extends Model
{
    use AppartientAAgence;

    public const TYPES = ['actualite' => 'Actualité', 'article' => 'Article', 'page' => 'Page'];

    protected $fillable = ['agence_id', 'type', 'categorie', 'titre', 'slug', 'resume', 'contenu', 'image', 'video_url', 'publie', 'publie_le', 'auteur_id'];

    protected $casts = ['publie' => 'boolean', 'publie_le' => 'datetime'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function scopeEnLigne(Builder $query): Builder
    {
        return $query->where('publie', true)->where(fn ($q) => $q->whereNull('publie_le')->orWhere('publie_le', '<=', now()));
    }
}
