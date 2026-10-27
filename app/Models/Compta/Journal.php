<?php

namespace App\Models\Compta;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    use AppartientAAgence;

    protected $table = 'cpt_journaux';

    protected $fillable = ['agence_id', 'code', 'libelle', 'type', 'actif'];

    protected $casts = ['actif' => 'boolean'];
}
