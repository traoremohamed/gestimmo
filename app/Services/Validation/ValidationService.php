<?php

namespace App\Services\Validation;

use App\Helpers\Audit;
use App\Models\User;
use App\Models\Validation\Circuit;
use App\Models\Validation\Decision;
use App\Models\Validation\Demande;
use App\Models\Validation\Etape;
use App\Services\Integration\NotificationService;
use App\Services\Numerotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Moteur générique de workflows de validation (repris de MARIEATTIEKESAAS).
 *
 *   if ($demande = ValidationService::intercepter('BAIL_ACTIVATION', $bail)) {
 *       return back()->with('info', "Demande {$demande->numero} soumise à validation.");
 *   }
 *   // ... sinon l'action s'exécute normalement
 *
 * Règles : circuit ACTIF de l'agence (le plus spécifique : filtre, puis version la plus récente) ; étapes dans l'ordre,
 * une étape ne s'applique que si le montant est dans sa plage ; validateur = profil, permission ou utilisateur nommé ;
 * le demandeur ne valide pas sa propre demande (séparation des tâches) et une même personne ne valide pas deux étapes ;
 * à la dernière approbation l'action est exécutée — une erreur d'exécution laisse la demande en ECHEC (relance possible).
 */
class ValidationService
{
    /** Exécution en cours d'une demande approuvée : pas de nouvelle interception. */
    protected static bool $enExecution = false;

    public static function circuit(string $action, ?string $filtre = null): ?Circuit
    {
        return Circuit::where('action', $action)->where('statut', 'ACTIF')
            ->where(fn ($q) => $q->whereNull('filtre')->when($filtre, fn ($w) => $w->orWhere('filtre', $filtre)))
            ->orderByRaw('filtre IS NULL')->orderByDesc('version')
            ->with('etapes')->first();
    }

    /** @return Collection<int, Etape> étapes qui s'appliquent au montant */
    public static function etapesApplicables(Circuit $circuit, ?float $montant): Collection
    {
        return $circuit->etapes->filter(fn (Etape $e) => $montant === null
            || (($e->montant_min === null || $montant >= (float) $e->montant_min) && ($e->montant_max === null || $montant <= (float) $e->montant_max)))->values();
    }

    /** L'action est-elle couverte par un circuit actif ? (sans créer de demande) */
    public static function soumise(string $action, ?Model $objet = null, ?float $montant = null, ?string $filtre = null): bool
    {
        if (self::$enExecution || !($circuit = self::circuit($action, $filtre))) {
            return false;
        }
        $montant ??= self::montant($action, $objet);
        if ($circuit->montant_declenchement !== null && $montant !== null && $montant < (float) $circuit->montant_declenchement) {
            return false;
        }

        return self::etapesApplicables($circuit, $montant)->isNotEmpty();
    }

    /**
     * Met l'action en attente si un circuit la couvre. Retourne null si l'action peut s'exécuter tout de suite.
     */
    public static function intercepter(string $action, ?Model $objet, array $donnees = [], ?string $motif = null, ?float $montant = null,
        ?string $filtre = null, ?string $resume = null): ?Demande
    {
        if (!Catalogue::existe($action) || !self::soumise($action, $objet, $montant, $filtre)) {
            return null;
        }
        $circuit = self::circuit($action, $filtre);
        $montant ??= self::montant($action, $objet);
        $etapes = self::etapesApplicables($circuit, $montant);

        $cle = $objet ? $action . ':' . $objet->getMorphClass() . ':' . $objet->getKey() : null;
        if ($cle && ($existante = Demande::where('cle_unicite', $cle)->first())) {
            return $existante;                                      // déjà en attente : pas de doublon
        }

        return DB::transaction(function () use ($action, $objet, $donnees, $motif, $montant, $filtre, $resume, $circuit, $etapes, $cle) {
            $demande = Demande::create([
                'numero' => app(Numerotation::class)->suivant('validation'),
                'circuit_id' => $circuit->id, 'circuit_version' => $circuit->version, 'action' => $action, 'filtre' => $filtre,
                'objet_type' => $objet?->getMorphClass(), 'objet_id' => $objet?->getKey(),
                'reference' => $objet ? self::reference($objet) : ($donnees['reference'] ?? null),
                'resume' => $resume ?? Catalogue::libelle($action) . ($objet ? ' — ' . self::reference($objet) : ''),
                'montant' => $montant, 'donnees' => $donnees ?: null, 'motif' => $motif,
                'statut' => 'EN_ATTENTE', 'etape_courante' => $etapes->first()->ordre,
                'echeance_etape_at' => $etapes->first()->delai_heures ? now()->addHours($etapes->first()->delai_heures) : null,
                'demandeur_id' => Auth::id(), 'demande_at' => now(), 'cle_unicite' => $cle,
            ]);
            Audit::logSave(['action' => 'SOUMETTRE A VALIDATION', 'code_piece' => $demande->numero, 'menu' => 'VALIDATIONS', 'etat' => 'Succès', 'objet' => 'VALIDATION',
                'objet_type' => $demande->objet_type, 'objet_id' => $demande->objet_id, 'nouvelles_valeurs' => ['action' => $action, 'montant' => $montant]]);
            self::notifier($demande, $etapes->first());

            return $demande;
        });
    }

    /**
     * Décision sur l'étape courante. Approbation de la dernière étape → exécution de l'action.
     */
    public static function decider(Demande $demande, string $decision, ?string $commentaire = null): Demande
    {
        if ($demande->statut !== 'EN_ATTENTE') {
            throw new RuntimeException('Cette demande n\'est plus en attente.');
        }
        $etape = self::etapeCourante($demande) ?? throw new RuntimeException('Étape introuvable : le circuit a été modifié.');
        if (!self::peutDecider(Auth::user(), $demande, $etape)) {
            throw new RuntimeException('Vous n\'êtes pas le validateur de l\'étape « ' . $etape->libelle . ' ».');
        }
        if ($decision === 'REJETE' && trim((string) $commentaire) === '') {
            throw new RuntimeException('Le motif du rejet est obligatoire.');
        }

        $approuvee = DB::transaction(function () use ($demande, $etape, $decision, $commentaire) {
            Decision::create(['demande_id' => $demande->id, 'etape_id' => $etape->id, 'ordre' => $etape->ordre, 'etape_libelle' => $etape->libelle,
                'decision' => $decision, 'commentaire' => $commentaire, 'decide_par' => Auth::id(), 'decide_at' => now()]);
            Audit::logSave(['action' => $decision === 'REJETE' ? 'REJETER' : 'APPROUVER', 'code_piece' => $demande->numero, 'menu' => 'VALIDATIONS', 'etat' => 'Succès',
                'objet' => 'VALIDATION', 'motif' => $commentaire]);

            if ($decision === 'REJETE') {
                $demande->update(['statut' => 'REJETEE', 'decide_at' => now(), 'etape_courante' => null, 'cle_unicite' => null]);
                $rejet = Str::camel(strtolower($demande->action)) . 'Rejet';
                if (method_exists(Actions::class, $rejet)) {
                    app(Actions::class)->$rejet($demande->objet, (string) $commentaire, $demande);
                }
                self::informerDemandeur($demande, 'rejetée : ' . $commentaire);

                return false;
            }
            $suivante = self::etapesApplicables($demande->circuit()->with('etapes')->first(), $demande->montant !== null ? (float) $demande->montant : null)
                ->first(fn ($e) => $e->ordre > $etape->ordre);
            if ($suivante) {
                $demande->update(['etape_courante' => $suivante->ordre, 'echeance_etape_at' => $suivante->delai_heures ? now()->addHours($suivante->delai_heures) : null]);
                self::notifier($demande, $suivante);

                return false;
            }
            $demande->update(['decide_at' => now(), 'etape_courante' => null, 'echeance_etape_at' => null]);

            return true;
        });

        return $approuvee ? self::executer($demande->fresh()) : $demande->fresh();
    }

    /** Exécute l'action approuvée (relançable après un ECHEC). */
    public static function executer(Demande $demande): Demande
    {
        if (!in_array($demande->statut, ['EN_ATTENTE', 'ECHEC'], true) || $demande->etape_courante !== null) {
            throw new RuntimeException('Seule une demande entièrement approuvée peut être exécutée.');
        }
        $methode = Str::camel(strtolower($demande->action));
        self::$enExecution = true;
        try {
            DB::transaction(fn () => app(Actions::class)->$methode($demande->objet, (array) $demande->donnees, $demande));
            $demande->update(['statut' => 'EXECUTEE', 'execute_at' => now(), 'erreur' => null, 'cle_unicite' => null]);
            self::informerDemandeur($demande, 'approuvée et exécutée');
        } catch (\Throwable $e) {
            $demande->update(['statut' => 'ECHEC', 'erreur' => Str::limit($e->getMessage(), 1000)]);
            self::informerDemandeur($demande, 'approuvée mais l\'exécution a échoué : ' . $e->getMessage());
        } finally {
            self::$enExecution = false;
        }

        return $demande->fresh();
    }

    public static function annuler(Demande $demande, string $motif): void
    {
        if ($demande->statut !== 'EN_ATTENTE') {
            throw new RuntimeException('Cette demande n\'est plus en attente.');
        }
        if ((int) $demande->demandeur_id !== (int) Auth::id() && !Auth::user()->can('validations-toutes')) {
            throw new RuntimeException('Seul le demandeur peut retirer sa demande.');
        }
        $demande->update(['statut' => 'ANNULEE', 'motif' => trim($demande->motif . "\nRetirée : " . $motif), 'cle_unicite' => null, 'etape_courante' => null]);
        $rejet = Str::camel(strtolower($demande->action)) . 'Rejet';
        if (method_exists(Actions::class, $rejet)) {
            app(Actions::class)->$rejet($demande->objet, 'Demande retirée : ' . $motif, $demande);
        }
    }

    public static function etapeCourante(Demande $demande): ?Etape
    {
        return $demande->etape_courante === null ? null : Etape::where('circuit_id', $demande->circuit_id)->where('ordre', $demande->etape_courante)->first();
    }

    public static function peutDecider(?User $user, Demande $demande, ?Etape $etape = null): bool
    {
        $etape ??= self::etapeCourante($demande);
        if (!$user || !$etape || $demande->statut !== 'EN_ATTENTE') {
            return false;
        }
        if ($etape->separation_auteur && (int) $demande->demandeur_id === (int) $user->id) {
            return false;
        }
        if ($demande->decisions()->where('decide_par', $user->id)->exists()) {
            return false;                                           // une personne = une seule étape
        }
        if ($etape->user_id && (int) $etape->user_id !== (int) $user->id) {
            return false;
        }
        if ($etape->role_id && !$user->roles->contains('id', $etape->role_id)) {
            return false;
        }
        if ($etape->permission_requise && !$user->can($etape->permission_requise)) {
            return false;
        }

        return $etape->user_id || $etape->role_id || $etape->permission_requise || $user->can('validations-toutes');
    }

    /** Demandes en attente que l'utilisateur peut décider. */
    public static function aDecider(User $user): Collection
    {
        return Demande::with(['circuit.etapes', 'demandeur'])->where('statut', 'EN_ATTENTE')->latest('demande_at')->get()
            ->filter(fn (Demande $d) => self::peutDecider($user, $d, $d->circuit->etapes->firstWhere('ordre', $d->etape_courante)))->values();
    }

    /** Demande en attente sur un objet (pour l'afficher sur sa fiche). */
    public static function enAttente(Model $objet, ?string $action = null): ?Demande
    {
        return Demande::where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())->where('statut', 'EN_ATTENTE')
            ->when($action, fn ($q) => $q->where('action', $action))->latest('id')->first();
    }

    public static function montant(string $action, ?Model $objet): ?float
    {
        $attribut = Catalogue::ACTIONS[$action][3] ?? null;
        if (!$objet || !$attribut) {
            return null;
        }
        $valeur = method_exists($objet, $attribut) ? $objet->{$attribut}() : $objet->{$attribut};

        return $valeur === null ? null : (float) $valeur;
    }

    protected static function reference(Model $objet): string
    {
        foreach (['numero', 'reference', 'login_users', 'matricule', 'code', 'libelle', 'nom_complet'] as $champ) {
            if (!empty($objet->{$champ})) {
                return (string) $objet->{$champ};
            }
        }

        return '#' . $objet->getKey();
    }

    /** Validateurs de l'étape prévenus par e-mail (modèle VALIDATION_A_DECIDER). */
    protected static function notifier(Demande $demande, Etape $etape): void
    {
        $validateurs = User::deLAgence($demande->agence_id)->where('flag_actif_users', true)->get()
            ->filter(fn (User $u) => self::peutDecider($u, $demande, $etape));
        foreach ($validateurs as $u) {
            try {
                NotificationService::notifier('VALIDATION_A_DECIDER', ['email' => $u->email, 'nom' => $u->nom_complet], [
                    'numero' => $demande->numero, 'resume' => $demande->resume, 'etape' => $etape->libelle,
                    'montant' => $demande->montant !== null ? \App\Support\Montant::formater($demande->montant) : '—',
                    'demandeur' => $demande->demandeur?->nom_complet, 'lien' => route('validations.show', $demande),
                ], $demande);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    protected static function informerDemandeur(Demande $demande, string $message): void
    {
        $u = $demande->demandeur;
        if (!$u?->email) {
            return;
        }
        try {
            NotificationService::notifier('VALIDATION_DECIDEE', ['email' => $u->email, 'nom' => $u->nom_complet],
                ['numero' => $demande->numero, 'resume' => $demande->resume, 'decision' => $message, 'lien' => route('validations.show', $demande)], $demande);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
