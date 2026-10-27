<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission rattachée à un sous-menu (table permissions).
 * Déclarée dans config/permission.php (models.permission).
 *
 * @property int $id
 * @property string $name            ex: role-index
 * @property string $lib_permission  ex: Voir
 * @property int|null $id_sousmenu
 * @property bool $is_valide
 * @property string $guard_name
 */
class Permissions extends SpatiePermission
{
    protected $table = 'permissions';

    protected $fillable = ['name', 'lib_permission', 'id_sousmenu', 'is_valide', 'guard_name'];

    protected $casts = ['is_valide' => 'boolean'];

    public function sousmenu(): BelongsTo
    {
        return $this->belongsTo(Sousmenus::class, 'id_sousmenu', 'id_sousmenu');
    }
}
