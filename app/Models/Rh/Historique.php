<?php

namespace App\Models\Rh;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Événement de carrière : embauche, promotion, mutation, augmentation, sanction, formation, évaluation, sortie.
 */
class Historique extends Model
{
    protected $table = 'rh_historique';

    public const TYPES = [
        'embauche' => 'Embauche', 'promotion' => 'Promotion', 'mutation' => 'Mutation', 'augmentation' => 'Augmentation',
        'sanction' => 'Sanction / avertissement', 'formation' => 'Formation', 'evaluation' => 'Évaluation annuelle', 'conges' => 'Acquisition de congés',
        'sortie' => 'Sortie',
    ];

    protected $fillable = ['employe_id', 'type', 'date_effet', 'libelle', 'details', 'user_id'];

    protected $casts = ['date_effet' => 'date', 'details' => 'array'];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
