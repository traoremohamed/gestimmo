<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodePaie extends Model
{
    use AppartientAAgence;

    protected $table = 'rh_periodes_paie';

    public const STATUTS = ['ouverte' => ['Ouverte', 'secondary'], 'calculee' => ['Calculée', 'info'], 'validee' => ['Validée', 'primary'],
        'payee' => ['Payée', 'success'], 'cloturee' => ['Clôturée', 'dark']];

    protected $fillable = ['agence_id', 'code', 'libelle', 'date_debut', 'date_fin', 'date_paiement', 'statut', 'total_brut', 'total_net', 'total_cout',
        'valide_par', 'valide_le'];

    protected $attributes = ['statut' => 'ouverte'];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date', 'date_paiement' => 'date', 'valide_le' => 'datetime',
        'total_brut' => 'decimal:2', 'total_net' => 'decimal:2', 'total_cout' => 'decimal:2'];

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class, 'periode_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(VariablePaie::class, 'periode_id');
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, ['ouverte', 'calculee'], true);
    }
}
