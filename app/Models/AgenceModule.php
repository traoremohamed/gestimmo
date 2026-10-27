<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgenceModule extends Model
{
    protected $fillable = ['agence_id', 'module', 'actif'];

    protected $casts = ['actif' => 'boolean'];
}
