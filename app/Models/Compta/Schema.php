<?php

namespace App\Models\Compta;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;

/**
 * Règle d'imputation : (opération, poste) => compte et journal.
 */
class Schema extends Model
{
    use AppartientAAgence;

    protected $table = 'cpt_schemas';

    public const OPERATIONS = ['TRESORERIE' => 'Comptes de trésorerie par mode de paiement', 'ENCAISSEMENT' => 'Encaissements (contrepartie)',
        'DEPENSE' => 'Dépenses (catégorie)', 'REVERSEMENT' => 'Reversements aux propriétaires', 'PAIE' => 'Paie'];

    protected $fillable = ['agence_id', 'operation', 'cle', 'libelle', 'compte', 'journal'];
}
