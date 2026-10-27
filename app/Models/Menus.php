<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module du menu (table menu).
 *
 * @property int $id_menu
 * @property string|null $code  repère stable (code du module métier, ou PARAMETRAGE / CONFIGURATION)
 * @property string $menu
 * @property int $priorite_menu
 * @property bool $is_valide
 * @property string|null $icone
 * @property bool $flag_is_admin  module de la plateforme (super administrateur)
 * @property string|null $module  module métier (config immobilier.modules), null = socle
 */
class Menus extends Model
{
    protected $table = 'menu';

    protected $primaryKey = 'id_menu';

    protected $fillable = ['code', 'menu', 'description', 'module', 'priorite_menu', 'is_valide', 'icone', 'flag_is_admin'];

    protected $casts = ['is_valide' => 'boolean', 'flag_is_admin' => 'boolean'];

    public function sousmenus(): HasMany
    {
        return $this->hasMany(Sousmenus::class, 'menu_id_menu', 'id_menu');
    }
}
