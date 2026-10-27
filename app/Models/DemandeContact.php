<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message reçu depuis le site vitrine (contact, demande d'information sur un bien, newsletter).
 */
class DemandeContact extends Model
{
    use AppartientAAgence;

    protected $table = 'demandes_contact';

    public const STATUTS = ['nouvelle' => 'Nouvelle', 'traitee' => 'Traitée', 'archivee' => 'Archivée'];

    protected $fillable = ['agence_id', 'bien_id', 'nom', 'email', 'telephone', 'sujet', 'message', 'newsletter', 'statut', 'reponse', 'traite_par', 'traite_le', 'ip'];

    protected $casts = ['newsletter' => 'boolean', 'traite_le' => 'datetime'];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class);
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
