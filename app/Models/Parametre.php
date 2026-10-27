<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;

/**
 * Valeur d'un paramètre métier pour une agence (voir App\Services\Parametres).
 */
class Parametre extends Model
{
    use AppartientAAgence;

    protected $fillable = ['agence_id', 'cle', 'valeur'];
}
