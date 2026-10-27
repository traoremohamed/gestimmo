<?php

namespace App\Models\Site;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloc d'une page (type déclaré dans config/site.php) : champs de base, options du type, éléments répétables.
 */
class Section extends Model
{
    use AppartientAAgence;

    protected $table = 'site_sections';

    protected $fillable = ['agence_id', 'page_id', 'type', 'ancre', 'dans_menu', 'titre', 'sous_titre', 'contenu', 'image', 'image_fond', 'couleur_fond', 'sombre',
        'options', 'elements', 'visible', 'ordre'];

    protected $casts = ['options' => 'array', 'elements' => 'array', 'dans_menu' => 'boolean', 'sombre' => 'boolean', 'visible' => 'boolean'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function libelle(): string
    {
        return config('site.types.' . $this->type . '.libelle', $this->type);
    }
}
