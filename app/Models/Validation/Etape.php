<?php

namespace App\Models\Validation;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Étape d'un circuit : validateur (profil, permission ou utilisateur nommé), plage de montant, délai.
 */
class Etape extends Model
{
    protected $table = 'wf_etapes';

    protected $fillable = ['circuit_id', 'ordre', 'libelle', 'role_id', 'permission_requise', 'user_id', 'montant_min', 'montant_max', 'separation_auteur', 'delai_heures'];

    protected $attributes = ['separation_auteur' => true];

    protected $casts = ['montant_min' => 'decimal:2', 'montant_max' => 'decimal:2', 'separation_auteur' => 'boolean'];

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Qui valide, en clair. */
    public function designation(): string
    {
        return collect([$this->role?->name, $this->validateur?->nom_complet, $this->permission_requise ? 'droit « ' . $this->permission_requise . ' »' : null])
            ->filter()->implode(' · ') ?: 'Tout validateur habilité';
    }
}
