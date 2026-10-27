<?php

namespace App\Models\Systeme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table sys_sauvegardes — historique des sauvegardes de la plateforme.
 */
class Sauvegarde extends Model
{
    protected $table = 'sys_sauvegardes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['contenu' => 'array', 'destinations' => 'array', 'manifeste' => 'array', 'chiffree' => 'boolean', 'debut_at' => 'datetime', 'fin_at' => 'datetime'];
    }

    public function lanceur(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'lance_par');
    }
}
