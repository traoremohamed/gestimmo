<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Compteur de numérotation (voir App\Services\Numerotation).
 */
class Sequence extends Model
{
    protected $fillable = ['agence_id', 'cle', 'annee', 'dernier_numero'];
}
