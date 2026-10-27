<?php

namespace App\Models\Integration;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;

/**
 * Texte d'une notification (par événement) pour l'e-mail et le SMS / WhatsApp, propre à chaque agence.
 */
class ModeleMessage extends Model
{
    use AppartientAAgence;

    protected $table = 'modeles_messages';

    protected $fillable = ['agence_id', 'code', 'libelle', 'canaux', 'sujet', 'corps_email', 'corps_sms', 'variables', 'actif'];

    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return ['canaux' => 'array', 'actif' => 'boolean'];
    }
}
