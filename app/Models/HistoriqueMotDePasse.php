<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoriqueMotDePasse extends Model
{
    protected $table = 'historique_mot_de_passe';

    protected $primaryKey = 'id_historique';

    protected $fillable = ['id_utilisateur', 'ancien_mot_de_passe_hash', 'date_changement'];
}
