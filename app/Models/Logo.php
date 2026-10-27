<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres système (table logo) : logo, couleurs, images, contacts...
 * "valeur" contient le type du paramètre (LOGO, COULEUR MENU HAUT...),
 * "mot_cle" sa valeur.
 *
 * @property int $id_logo
 * @property string|null $titre_logo
 * @property string|null $logo_logo  nom du fichier dans public/frontend/logo
 * @property bool $flag_logo         actif / inactif
 * @property int $id_user
 * @property string|null $mot_cle
 * @property string|null $valeur
 */
class Logo extends Model
{
    protected $table = 'logo';

    protected $primaryKey = 'id_logo';

    protected $fillable = ['titre_logo', 'logo_logo', 'flag_logo', 'id_user', 'mot_cle', 'valeur'];

    protected $casts = ['flag_logo' => 'boolean'];

    public function scopeActifs($query, string $type)
    {
        return $query->where('flag_logo', true)->where('valeur', $type);
    }
}
