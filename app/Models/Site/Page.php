<?php

namespace App\Models\Site;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Page du site vitrine : la page d'accueil (adresse racine) ou une page libre composée de blocs.
 */
class Page extends Model
{
    use AppartientAAgence;

    protected $table = 'site_pages';

    protected $fillable = ['agence_id', 'site_id', 'slug', 'titre', 'est_accueil', 'dans_menu', 'publie', 'seo_titre', 'seo_description', 'ordre'];

    protected $casts = ['est_accueil' => 'boolean', 'dans_menu' => 'boolean', 'publie' => 'boolean'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('ordre')->orderBy('id');
    }
}
