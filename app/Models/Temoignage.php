<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;

class Temoignage extends Model
{
    use AppartientAAgence;

    protected $fillable = ['agence_id', 'auteur', 'fonction', 'contenu', 'photo', 'note', 'actif'];

    protected $casts = ['actif' => 'boolean'];
}
