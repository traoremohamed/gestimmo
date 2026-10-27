<?php

namespace App\Models\Site;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Réglages du site vitrine d'une agence (un site par agence).
 */
class Site extends Model
{
    use AppartientAAgence;

    protected $table = 'site_sites';

    protected $fillable = ['agence_id', 'titre', 'slogan', 'description', 'mots_cles', 'couleur_principale', 'couleur_secondaire', 'police', 'theme', 'logo', 'favicon',
        'contact', 'reseaux', 'horaires', 'carte_url', 'pied_page', 'css', 'lien_espace_client', 'menu_biens', 'menu_actualites'];

    protected $casts = ['contact' => 'array', 'reseaux' => 'array', 'lien_espace_client' => 'boolean', 'menu_biens' => 'boolean', 'menu_actualites' => 'boolean'];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderByDesc('est_accueil')->orderBy('ordre');
    }
}
