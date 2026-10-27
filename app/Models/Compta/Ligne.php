<?php

namespace App\Models\Compta;

use App\Models\Tiers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ligne extends Model
{
    protected $table = 'cpt_lignes';

    protected $fillable = ['ecriture_id', 'compte_id', 'libelle', 'debit', 'credit', 'tiers_id', 'lettrage'];

    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function ecriture(): BelongsTo
    {
        return $this->belongsTo(Ecriture::class);
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class);
    }

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }
}
