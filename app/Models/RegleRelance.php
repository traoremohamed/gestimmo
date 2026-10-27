<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;

/**
 * Niveau de relance paramétrable : déclenché à J+delai_jours de retard.
 */
class RegleRelance extends Model
{
    use AppartientAAgence;

    protected $table = 'regles_relance';

    public const CANAUX = ['email' => 'E-mail', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'courrier' => 'Courrier (PDF)', 'appel' => 'Appel téléphonique'];

    protected $fillable = ['agence_id', 'niveau', 'libelle', 'delai_jours', 'canal', 'activites', 'objet', 'message', 'automatique', 'actif'];

    protected $casts = ['automatique' => 'boolean', 'actif' => 'boolean'];

    public function concerne(string $activite): bool
    {
        return !$this->activites || in_array($activite, explode(',', $this->activites), true);
    }
}
