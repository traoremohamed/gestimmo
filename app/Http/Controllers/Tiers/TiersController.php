<?php

namespace App\Http\Controllers\Tiers;

use App\Enums\RoleTiers;
use App\Helpers\Audit;
use App\Http\Controllers\CrudController;
use App\Models\Pays;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Models\TiersPiece;
use App\Models\User;
use App\Services\Finance\EncaissementService;
use App\Services\Integration\NotificationService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TiersController extends CrudController
{
    protected string $modele = Tiers::class;

    protected string $route = 'tiers';

    protected string $titre = 'Tiers';

    protected string $titreSingulier = 'Tiers';

    protected string $module = 'Tiers & CRM';

    protected array $recherche = ['nom', 'prenoms', 'raison_sociale', 'telephone', 'email', 'code'];

    protected string $tri = 'nom';

    protected string $sens = 'asc';

    protected function champs(?Model $element = null): array
    {
        $users = fn () => User::where('agence_id', agence()?->id)->where('flag_actif_users', true)->orderBy('name')->get()->pluck('nom_complet', 'id');

        return [
            ['nom' => '_t1', 'label' => 'Identité', 'type' => 'titre'],
            ['nom' => 'roles', 'label' => 'Rôles', 'type' => 'select', 'multiple' => true, 'options' => RoleTiers::options(), 'col' => 'col-md-8',
                'regles' => ['required', 'array', 'min:1'], 'defaut' => $element?->roles() ?? [request('role', 'prospect')], 'requis' => true],
            ['nom' => 'type_personne', 'label' => 'Type', 'type' => 'select', 'options' => ['physique' => 'Personne physique', 'morale' => 'Personne morale'], 'requis' => true, 'col' => 'col-md-4', 'defaut' => 'physique'],
            ['nom' => 'civilite', 'label' => 'Civilité', 'type' => 'select', 'options' => ['M.' => 'M.', 'Mme' => 'Mme', 'Mlle' => 'Mlle', 'Dr' => 'Dr', 'Me' => 'Me'], 'col' => 'col-md-2'],
            ['nom' => 'nom', 'label' => 'Nom', 'requis' => true, 'col' => 'col-md-4'],
            ['nom' => 'prenoms', 'label' => 'Prénoms', 'col' => 'col-md-6'],
            ['nom' => 'raison_sociale', 'label' => 'Raison sociale (personne morale)', 'col' => 'col-md-6'],
            ['nom' => 'representant_legal', 'label' => 'Représentant légal', 'col' => 'col-md-6'],
            ['nom' => 'sexe', 'label' => 'Sexe', 'type' => 'select', 'options' => ['M' => 'Masculin', 'F' => 'Féminin'], 'col' => 'col-md-2'],
            ['nom' => 'date_naissance', 'label' => 'Date de naissance', 'type' => 'date', 'col' => 'col-md-3', 'regles' => ['nullable', 'date', 'before:today']],
            ['nom' => 'lieu_naissance', 'label' => 'Lieu de naissance', 'col' => 'col-md-3'],
            ['nom' => 'nationalite_id', 'label' => 'Nationalité', 'type' => 'select', 'options' => fn () => Pays::orderBy('nationalite')->pluck('nationalite', 'id'), 'col' => 'col-md-4', 'recherche' => true],
            ['nom' => 'situation_matrimoniale', 'label' => 'Situation matrimoniale', 'type' => 'select', 'options' => fn () => Referentiel::options('situation_matrimoniale'), 'col' => 'col-md-4'],
            ['nom' => 'regime_matrimonial', 'label' => 'Régime matrimonial', 'type' => 'select', 'options' => ['communaute' => 'Communauté de biens', 'separation' => 'Séparation de biens'], 'col' => 'col-md-4'],
            ['nom' => 'profession', 'label' => 'Profession', 'col' => 'col-md-4'],
            ['nom' => 'employeur', 'label' => 'Employeur', 'col' => 'col-md-4'],

            ['nom' => '_t2', 'label' => 'Coordonnées', 'type' => 'titre'],
            ['nom' => 'indicatif', 'label' => 'Indicatif', 'col' => 'col-md-2', 'defaut' => '225', 'regles' => ['nullable', 'regex:/^\d{1,4}$/']],
            ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'col' => 'col-md-4'],
            ['nom' => 'telephone2', 'label' => 'Téléphone 2', 'type' => 'tel', 'col' => 'col-md-3'],
            ['nom' => 'email', 'label' => 'E-mail', 'type' => 'email', 'col' => 'col-md-3'],
            ['nom' => 'adresse', 'label' => 'Adresse / domicile', 'col' => 'col-md-6'],
            ['nom' => 'ville', 'label' => 'Ville', 'col' => 'col-md-3'],
            ['nom' => 'boite_postale', 'label' => 'Boîte postale', 'col' => 'col-md-3'],
            ['nom' => 'pays_id', 'label' => 'Pays de résidence', 'type' => 'select', 'options' => fn () => Pays::orderBy('libelle')->pluck('libelle', 'id'), 'col' => 'col-md-4', 'recherche' => true],

            ['nom' => '_t3', 'label' => 'Suivi commercial & informations complémentaires', 'type' => 'titre'],
            ['nom' => 'canal_acquisition', 'label' => 'Canal d\'acquisition', 'type' => 'select', 'options' => fn () => Referentiel::options('canal_acquisition'), 'col' => 'col-md-4'],
            ['nom' => 'commercial_id', 'label' => 'Commercial / gestionnaire', 'type' => 'select', 'options' => $users, 'col' => 'col-md-4'],
            ['nom' => 'corps_metier', 'label' => 'Corps de métier (fournisseur)', 'type' => 'select', 'options' => fn () => Referentiel::options('corps_metier'), 'col' => 'col-md-4'],
            ['nom' => 'rccm', 'label' => 'RCCM', 'col' => 'col-md-3'],
            ['nom' => 'compte_contribuable', 'label' => 'Compte contribuable', 'col' => 'col-md-3'],
            ['nom' => 'banque', 'label' => 'Banque (reversements)', 'col' => 'col-md-3'],
            ['nom' => 'rib', 'label' => 'RIB / n° de compte', 'col' => 'col-md-3'],
            ['nom' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Tiers', fn ($t) => Cellule::lien($t->nom_complet, route('tiers.show', $t))],
            ['Code', fn ($t) => $t->code],
            ['Contact', fn ($t) => $t->contact],
            ['Rôles', fn ($t) => new \Illuminate\Support\HtmlString(collect($t->roles())->map(fn ($r) => Cellule::badge(RoleTiers::from($r)->libelle(), RoleTiers::from($r)->couleur()))->implode(' '))],
            ['Commercial', fn ($t) => $t->commercial?->nom_complet],
        ];
    }

    protected function filtres(): array
    {
        return [
            ['nom' => 'role', 'label' => 'Rôle', 'options' => RoleTiers::options()],
            ['nom' => 'canal_acquisition', 'label' => 'Canal', 'options' => fn () => Referentiel::options('canal_acquisition')],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->with('commercial')
            ->when($request->filled('role'), fn ($q) => $q->avecRole($request->role))
            ->when($request->filled('canal_acquisition'), fn ($q) => $q->where('canal_acquisition', $request->canal_acquisition));
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $roles = $donnees['roles'];
        unset($donnees['roles']);

        $tiers = DB::transaction(function () use ($donnees, $roles) {
            $tiers = Tiers::create($donnees + ['code' => $this->prochainCode(), 'created_by' => auth()->id()]);
            $tiers->synchroniserRoles($roles);

            return $tiers;
        });

        $this->auditer('CREER', $tiers);

        return redirect()->route('tiers.show', $tiers)->with('success', 'Tiers ' . $tiers->nom_complet . ' enregistré.');
    }

    public function update(Request $request, string $id)
    {
        $tiers = $this->trouver($id);
        $donnees = $this->valider($request, $tiers);
        $roles = $donnees['roles'];
        unset($donnees['roles']);

        DB::transaction(function () use ($tiers, $donnees, $roles) {
            $tiers->update($donnees);
            $tiers->synchroniserRoles($roles);
        });

        $this->auditer('MODIFIER', $tiers);

        return redirect()->route('tiers.show', $tiers)->with('success', 'Tiers modifié.');
    }

    public function show(string $id)
    {
        $tiers = $this->trouver($id);
        $tiers->load(['pieces', 'ventes.biens', 'baux.bien', 'biensEnGestion', 'mandats', 'lotsCopropriete.copropriete', 'prospects', 'sejours.bien', 'compte']);

        return view('tiers.show', [
            'tiers' => $tiers,
            'encaissements' => $tiers->encaissements()->latest('date_encaissement')->limit(15)->get(),
            'rendezVous' => $tiers->rendezVous()->latest('debut')->limit(10)->get(),
            'dettes' => app(EncaissementService::class)->dettesOuvertes($tiers),
        ]);
    }

    protected function supprimable(Model $element): bool
    {
        return !$element->ventes()->exists() && !$element->baux()->exists() && !$element->encaissements()->exists()
            && !$element->biensEnGestion()->exists() && !$element->lotsCopropriete()->exists();
    }

    /**
     * Recherche pour les listes déroulantes (select2) : ?q=&role=
     */
    public function recherche(Request $request)
    {
        $resultats = Tiers::recherche($request->string('q'))
            ->when($request->filled('role'), fn ($q) => $q->avecRole(...explode(',', $request->role)))
            ->orderBy('nom')->limit(20)->get();

        return response()->json(['results' => $resultats->map(fn ($t) => ['id' => $t->id, 'text' => $t->nom_complet . ($t->telephone ? ' — ' . $t->telephone : '')])]);
    }

    public function ajouterPiece(Request $request, Tiers $tiers)
    {
        $donnees = $request->validate([
            'type_piece' => ['required', Rule::in(Referentiel::options('type_piece_identite')->keys())],
            'numero' => ['required', 'string', 'max:100'],
            'date_delivrance' => ['nullable', 'date'],
            'date_expiration' => ['nullable', 'date', 'after_or_equal:date_delivrance'],
            'lieu_delivrance' => ['nullable', 'string', 'max:255'],
            'fichier' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($request->hasFile('fichier')) {
            $donnees['fichier'] = $request->file('fichier')->store('agences/' . agence()->id . '/pieces', config('immobilier.stockage.prive'));
        }

        $tiers->pieces()->create($donnees);

        return back()->with('success', 'Pièce ajoutée.');
    }

    public function telechargerPiece(Tiers $tiers, TiersPiece $piece)
    {
        abort_unless($piece->tiers_id === $tiers->id && $piece->fichier, 404);

        return Storage::disk(config('immobilier.stockage.prive'))->response($piece->fichier);
    }

    public function supprimerPiece(Tiers $tiers, TiersPiece $piece)
    {
        abort_unless($piece->tiers_id === $tiers->id, 404);

        if ($piece->fichier) {
            Storage::disk(config('immobilier.stockage.prive'))->delete($piece->fichier);
        }
        $piece->delete();

        return back()->with('success', 'Pièce supprimée.');
    }

    /**
     * Ouvre l'espace client du tiers (profil CLIENT) et lui envoie ses identifiants.
     */
    public function creerCompte(Tiers $tiers)
    {
        if ($tiers->user_id) {
            return back()->with('error', 'Ce tiers a déjà un compte.');
        }
        if (!$tiers->email) {
            return back()->with('error', 'Renseignez l\'e-mail du tiers pour lui créer un compte.');
        }
        if (User::where('email', $tiers->email)->exists()) {
            return back()->with('error', 'Un compte utilise déjà cet e-mail.');
        }

        $motDePasse = Str::password(12, symbols: false);
        $user = User::create([
            'agence_id' => agence()->id,
            'name' => $tiers->nom,
            'prenom_users' => $tiers->prenoms,
            'email' => $tiers->email,
            'login_users' => Str::lower(Str::before($tiers->email, '@')) . $tiers->id,
            'cel_users' => $tiers->telephone,
            'password' => $motDePasse,
            'flag_actif_users' => true,
            'flag_mdp' => false,
        ]);
        $user->assignRole('CLIENT');
        $tiers->update(['user_id' => $user->id]);

        try {
            NotificationService::notifier('COMPTE_CLIENT', ['email' => $user->email, 'telephone' => $tiers->telephone, 'nom' => $tiers->nom_complet],
                ['identifiant' => $user->login_users, 'mot_de_passe' => $motDePasse, 'lien' => url('/connexion')], $tiers);
        } catch (\Throwable $e) {
            report($e);
        }

        Audit::logSave(['action' => 'CREER COMPTE CLIENT', 'code_piece' => $tiers->id, 'menu' => 'TIERS', 'etat' => 'Succès', 'objet' => 'TIERS & CRM']);

        return back()->with('success', 'Compte client créé (identifiant : ' . $user->login_users . '). Les identifiants ont été envoyés par e-mail.');
    }

    protected function prochainCode(): string
    {
        $prefixe = parametre('numerotation.tiers') ?: 'T';
        $numero = Tiers::withTrashed()->count();

        do {
            $code = sprintf('%s-%06d', $prefixe, ++$numero);
        } while (Tiers::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
