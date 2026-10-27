<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campagne extends Model
{
    use AppartientAAgence;

    public const CANAUX = ['email' => 'E-mail', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'];

    protected $fillable = ['agence_id', 'nom', 'canal', 'objet', 'message', 'cible', 'statut', 'envoyee_le', 'nb_destinataires', 'created_by'];

    protected $casts = ['cible' => 'array', 'envoyee_le' => 'datetime'];

    public function destinataires(): HasMany
    {
        return $this->hasMany(CampagneDestinataire::class);
    }
}
