<?php

namespace App\Models;

use App\Support\AgenceCourante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Profil utilisateur (table roles), déclaré dans config/permission.php.
 *
 *  - agence_id vide : profil modèle de la plateforme, proposé à toutes les agences
 *    (modifiable par le seul super administrateur) ;
 *  - agence_id renseigné : profil propre à une agence, créé librement ou à partir d'un modèle.
 *
 * Une agence ne voit que ses profils et les modèles ; le profil SUPER ADMINISTRATEUR n'est jamais proposé.
 *
 * @property int $id
 * @property int|null $agence_id
 * @property string $name
 * @property string|null $codes_roles_detail  SPADMIN = super administrateur
 */
class Role extends SpatieRole
{
    public const SPADMIN = 'SPADMIN';

    protected $fillable = ['name', 'guard_name', 'code_roles', 'code_fonction', 'codes_roles_detail', 'agence_id', 'description', 'page_accueil'];

    /**
     * Sous-menus visibles par le profil (table role_has_sousmenus).
     */
    public function sousmenus(): BelongsToMany
    {
        return $this->belongsToMany(Sousmenus::class, 'role_has_sousmenus', 'role_id', 'sousmenus_id_sousmenu');
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    /**
     * Profils visibles : ceux de l'agence courante et les modèles (hors SPADMIN).
     * Super administrateur hors agence : tous les profils.
     */
    public function scopeVisiblesPour(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $agenceId = app(AgenceCourante::class)->id();
        if ($user?->est_super_admin && $agenceId === null) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('agence_id', $agenceId ?? 0)->orWhereNull('agence_id'))
            ->where(fn ($q) => $q->where('codes_roles_detail', '!=', self::SPADMIN)->orWhereNull('codes_roles_detail'));
    }

    public function estModele(): bool
    {
        return $this->agence_id === null;
    }

    public function estSuperAdmin(): bool
    {
        return $this->codes_roles_detail === self::SPADMIN;
    }
}
