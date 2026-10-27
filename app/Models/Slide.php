<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;

class Slide extends Model
{
    use AppartientAAgence;

    protected $fillable = ['agence_id', 'titre', 'sous_titre', 'image', 'video_url', 'bouton_libelle', 'bouton_lien', 'ordre', 'actif'];

    protected $casts = ['actif' => 'boolean'];
}
