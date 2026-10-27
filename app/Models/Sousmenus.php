<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sous-module du menu (table sousmenu), imbricable sur un nombre illimité de niveaux (repris du SIH).
 *
 * @property int $id_sousmenu
 * @property int $menu_id_menu
 * @property int|null $parent_id  sous-menu parent (NULL = premier niveau du module)
 * @property string|null $code  repère stable pour les seeders
 * @property string|null $sousmenu  url / nom de route (ex: roles) ; NULL = simple groupe
 * @property string $libelle
 * @property string|null $icone
 * @property int $priorite_sousmenu
 * @property bool $is_valide
 * @property bool $flag_is_admin  écran de la plateforme (jamais attribué à un profil d'agence)
 */
class Sousmenus extends Model
{
    protected $table = 'sousmenu';

    protected $primaryKey = 'id_sousmenu';

    protected $fillable = ['menu_id_menu', 'parent_id', 'code', 'sousmenu', 'libelle', 'icone', 'description', 'priorite_sousmenu', 'is_valide', 'flag_is_admin'];

    protected $casts = ['is_valide' => 'boolean', 'flag_is_admin' => 'boolean'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menus::class, 'menu_id_menu', 'id_menu');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id_sousmenu');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id_sousmenu')->orderBy('priorite_sousmenu');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_sousmenus', 'sousmenus_id_sousmenu', 'role_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permissions::class, 'id_sousmenu', 'id_sousmenu');
    }

    public function estGroupe(): bool
    {
        return trim((string) $this->sousmenu) === '';
    }

    /**
     * Identifiants de tous les descendants (pour interdire qu'un nœud devienne son propre ancêtre).
     *
     * @return array<int>
     */
    public function descendantsIds(): array
    {
        $ids = [];
        $niveau = [$this->id_sousmenu];
        while ($niveau) {
            $niveau = static::query()->whereIn('parent_id', $niveau)->pluck('id_sousmenu')->all();
            $ids = array_merge($ids, $niveau);
        }

        return $ids;
    }

    /** Chemin lisible : PARAMETRAGE › Agence › Modules */
    public function chemin(): string
    {
        $parties = [$this->libelle];
        $courant = $this;
        for ($i = 0; $i < 20 && $courant->parent_id; $i++) {
            $courant = static::find($courant->parent_id);
            if (!$courant) {
                break;
            }
            array_unshift($parties, $courant->libelle);
        }

        return implode(' › ', $parties);
    }
}
