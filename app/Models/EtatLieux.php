<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtatLieux extends Model
{
    use AppartientAAgence;

    protected $table = 'etats_lieux';

    public const ETATS = ['neuf' => 'Neuf', 'bon' => 'Bon état', 'usage' => 'Usage normal', 'mauvais' => 'Mauvais état'];

    protected $fillable = ['agence_id', 'bail_id', 'type', 'date', 'pieces', 'releves_compteurs', 'compteurs', 'reparations', 'observations', 'user_id'];

    protected $casts = ['date' => 'date', 'pieces' => 'array', 'compteurs' => 'array', 'reparations' => 'array'];

    public function bail(): BelongsTo
    {
        return $this->belongsTo(Bail::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
