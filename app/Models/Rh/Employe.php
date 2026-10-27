<?php

namespace App\Models\Rh;

use App\Models\Concerns\AppartientAAgence;
use App\Models\Pays;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dossier d'un membre du personnel (indépendant du compte utilisateur, qui est facultatif).
 */
class Employe extends Model
{
    use AppartientAAgence, SoftDeletes;

    protected $table = 'rh_employes';

    public const STATUTS = ['actif' => ['Actif', 'success'], 'suspendu' => ['Suspendu', 'warning'], 'sorti' => ['Sorti', 'secondary']];

    public const SITUATIONS = ['CELIBATAIRE' => 'Célibataire', 'MARIE' => 'Marié(e)', 'DIVORCE' => 'Divorcé(e)', 'VEUF' => 'Veuf / veuve'];

    protected $fillable = [
        'agence_id', 'user_id', 'matricule', 'civilite', 'nom', 'prenoms', 'sexe', 'date_naissance', 'lieu_naissance', 'nationalite_id', 'piece_identite',
        'telephone', 'email', 'adresse', 'departement', 'poste', 'situation_familiale', 'nombre_enfants', 'nombre_parts', 'numero_cnps', 'contact_urgence',
        'banque', 'rib', 'date_embauche', 'date_sortie', 'motif_sortie', 'solde_conges', 'statut', 'observations',
    ];

    protected $attributes = ['statut' => 'actif', 'nombre_parts' => 1, 'solde_conges' => 0];

    protected $casts = [
        'date_naissance' => 'date', 'date_embauche' => 'date', 'date_sortie' => 'date', 'nombre_parts' => 'decimal:1', 'solde_conges' => 'decimal:2',
    ];

    public function getNomCompletAttribute(): string
    {
        return trim($this->nom . ' ' . $this->prenoms);
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nationalite(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'nationalite_id');
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function contratActif(): HasOne
    {
        return $this->hasOne(Contrat::class)->where('statut', 'actif')->latestOfMany('date_debut');
    }

    public function historique(): HasMany
    {
        return $this->hasMany(Historique::class)->orderByDesc('date_effet');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function avances(): HasMany
    {
        return $this->hasMany(Avance::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    /**
     * Parts fiscales proposées (ITS) : 2 si marié, sinon 1, plus 0,5 par enfant à charge, plafonné à 5.
     * Valeur modifiable sur la fiche de l'employé pour les cas particuliers.
     */
    public static function partsFiscales(?string $situation, int $enfants): float
    {
        return min(5.0, ($situation === 'MARIE' ? 2.0 : 1.0) + 0.5 * max(0, $enfants));
    }

    public function ancienneteAnnees(?\DateTimeInterface $date = null): int
    {
        return (int) $this->date_embauche->diffInYears($date ?? today());
    }
}
