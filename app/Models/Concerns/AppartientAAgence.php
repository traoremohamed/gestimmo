<?php

namespace App\Models\Concerns;

use App\Models\Agence;
use App\Support\AgenceCourante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolation multi-agences : filtre toutes les requêtes sur l'agence courante
 * et renseigne agence_id à la création.
 */
trait AppartientAAgence
{
    public static function bootAppartientAAgence(): void
    {
        static::addGlobalScope('agence', function (Builder $query) {
            $agenceId = app(AgenceCourante::class)->id();

            if ($agenceId !== null) {
                $query->where($query->getModel()->qualifyColumn('agence_id'), $agenceId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->agence_id)) {
                $model->agence_id = app(AgenceCourante::class)->id()
                    ?? throw new \LogicException('Aucune agence courante pour créer ' . static::class);
            }
        });
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function scopeToutesAgences(Builder $query): Builder
    {
        return $query->withoutGlobalScope('agence');
    }
}
