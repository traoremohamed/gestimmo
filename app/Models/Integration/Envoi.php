<?php

namespace App\Models\Integration;

use App\Support\AgenceCourante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal des envois (e-mail, SMS, WhatsApp). Écriture seule.
 * Hors agence (super administrateur), tous les envois sont visibles.
 */
class Envoi extends Model
{
    protected $table = 'envois';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('agence', function (Builder $query) {
            if ($agenceId = app(AgenceCourante::class)->id()) {
                $query->where('agence_id', $agenceId);
            }
        });
    }

    protected function casts(): array
    {
        return ['envoye_at' => 'datetime'];
    }
}
