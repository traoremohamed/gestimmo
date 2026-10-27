<?php

namespace App\Models;

use App\Support\AgenceCourante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Utilisateur de l'application.
 *  - utilisateur d'agence : agence_id renseigné (agences supplémentaires dans user_agences), droits donnés par ses profils ;
 *  - super administrateur de la plateforme (est_super_admin) : tous les droits (Gate::before), administre toutes les agences.
 */
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    protected $fillable = [
        'agence_id', 'name', 'prenom_users', 'email', 'password', 'genre_users', 'cel_users', 'login_users',
        'tel_users', 'localisation_users', 'adresse_users', 'id_partenaire', 'flag_mdp', 'flag_actif_users',
        'photo_profil', 'indicatif_cel_users', 'indicatif_tel_users', 'num_agce', 'email_agce', 'id_direction',
        'id_departement', 'id_service', 'flag_mod_pswd_first', 'flag_admin_users', 'flag_demission_users',
        'fonction', 'date_debut_acces', 'date_fin_acces', 'created_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'est_super_admin' => 'boolean',
            'flag_mdp' => 'boolean',
            'flag_actif_users' => 'boolean',
            'flag_admin_users' => 'boolean',
            'flag_demission_users' => 'boolean',
            'flag_mod_pswd_first' => 'boolean',
            'date_debut_acces' => 'date',
            'date_fin_acces' => 'date',
            'derniere_connexion_at' => 'datetime',
            'verrouille_jusqu_a' => 'datetime',
            'mot_de_passe_change_at' => 'datetime',
        ];
    }

    public function getAvatarAttribute(): string
    {
        return $this->photo_profil ? asset('photoprofile/' . $this->photo_profil) : asset('assets/img/avatars/1.png');
    }

    public function getInitialesAttribute(): string
    {
        return mb_strtoupper(mb_substr((string) $this->name, 0, 1) . mb_substr((string) $this->prenom_users, 0, 1));
    }

    public function historiqueMotDePasse()
    {
        return $this->hasMany(HistoriqueMotDePasse::class, 'id_utilisateur');
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    /** Agences supplémentaires accessibles (en plus de l'agence principale). */
    public function agences(): BelongsToMany
    {
        return $this->belongsToMany(Agence::class, 'user_agences')->withTimestamps();
    }

    /**
     * Fiche tiers liée au compte (espace client : acquéreur, locataire, propriétaire, copropriétaire).
     */
    public function tiers()
    {
        return $this->hasOne(Tiers::class, 'user_id')->withoutGlobalScope('agence');
    }

    /** Super administrateur de la plateforme : tous les droits, toutes les agences. */
    public function estSuperAdmin(): bool
    {
        return (bool) $this->est_super_admin;
    }

    /** Utilisateur de la plateforme (administration SaaS). */
    public function estPlateforme(): bool
    {
        return $this->estSuperAdmin();
    }

    /** @return int[] agences dans lesquelles l'utilisateur peut travailler */
    public function agencesAutorisees(): array
    {
        return array_values(array_unique(array_filter([$this->agence_id, ...$this->agences()->pluck('agences.id')->all()])));
    }

    /** Compte actif, dans sa période d'accès, agence active et abonnement valide. */
    public function accesAutorise(): bool
    {
        if (!$this->flag_actif_users || $this->flag_demission_users) {
            return false;
        }
        if (($this->date_debut_acces && $this->date_debut_acces->isFuture()) || ($this->date_fin_acces && $this->date_fin_acces->endOfDay()->isPast())) {
            return false;
        }

        return $this->estSuperAdmin() || (bool) $this->agence?->abonnementValide();
    }

    /** Utilisateurs de l'agence courante (les super administrateurs n'y figurent jamais). */
    public function scopeDeLAgence(Builder $query, ?int $agenceId = null): Builder
    {
        $agenceId ??= app(AgenceCourante::class)->id() ?? 0;

        return $query->where('est_super_admin', false)
            ->where(fn ($q) => $q->where('agence_id', $agenceId)->orWhereIn('id', fn ($s) => $s->select('user_id')->from('user_agences')->where('agence_id', $agenceId)));
    }

    public function getNomCompletAttribute(): string
    {
        return trim($this->name . ' ' . $this->prenom_users);
    }
}
