<?php

namespace App\Http\Controllers\Validation;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Permissions;
use App\Models\Role;
use App\Models\User;
use App\Models\Validation\Circuit;
use App\Models\Validation\Etape;
use App\Services\Validation\Catalogue;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Circuits de validation de l'agence, paramétrables à l'écran (gouvernance reprise de MARIEATTIEKESAAS) :
 * un circuit est créé en BROUILLON, approuvé par un autre utilisateur, puis ACTIVÉ (l'éventuelle version
 * précédente est RETIRÉE). Un circuit actif n'est plus modifiable : on en crée une nouvelle version.
 */
class CircuitController extends Controller
{
    public function index()
    {
        return view('validations.circuits', [
            'circuits' => Circuit::withCount('etapes')->with('auteur')->orderBy('action')->orderByDesc('version')->get(),
            'actions' => Catalogue::options(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $circuit = Circuit::create($donnees + ['created_by' => Auth::id()]);
        Audit::logSave(['action' => 'CREER CIRCUIT', 'code_piece' => $circuit->id, 'menu' => 'CIRCUITS DE VALIDATION', 'etat' => 'Succès', 'objet' => 'VALIDATION', 'nouvelles_valeurs' => $donnees]);

        return redirect()->route('circuits.show', $circuit)->with('success', 'Circuit créé : ajoutez maintenant ses étapes.');
    }

    public function show(Circuit $circuit)
    {
        return view('validations.circuit', [
            'circuit' => $circuit->load(['etapes.role', 'etapes.validateur', 'auteur', 'approbateur']),
            'actions' => Catalogue::options(),
            'roles' => Role::visiblesPour()->orderBy('name')->pluck('name', 'id'),
            'utilisateurs' => User::deLAgence()->where('flag_actif_users', true)->orderBy('name')->get()->pluck('nom_complet', 'id'),
            'permissions' => Permissions::orderBy('name')->pluck('name', 'name'),
            'versions' => Circuit::where('action', $circuit->action)->where('filtre', $circuit->filtre)->orderByDesc('version')->get(),
        ]);
    }

    public function update(Request $request, Circuit $circuit)
    {
        $this->modifiable($circuit);
        $circuit->update($this->valider($request, $circuit));

        return back()->with('success', 'Circuit enregistré.');
    }

    public function ajouterEtape(Request $request, Circuit $circuit)
    {
        $this->modifiable($circuit);
        $donnees = $this->validerEtape($request);
        $circuit->etapes()->create($donnees + ['ordre' => (int) $circuit->etapes()->max('ordre') + 1]);

        return back()->with('success', 'Étape ajoutée.');
    }

    public function modifierEtape(Request $request, Circuit $circuit, Etape $etape)
    {
        $this->modifiable($circuit);
        abort_unless($etape->circuit_id === $circuit->id, 404);
        $etape->update($this->validerEtape($request));

        return back()->with('success', 'Étape enregistrée.');
    }

    public function supprimerEtape(Circuit $circuit, Etape $etape)
    {
        $this->modifiable($circuit);
        abort_unless($etape->circuit_id === $circuit->id, 404);
        DB::transaction(function () use ($circuit, $etape) {
            $etape->delete();
            foreach ($circuit->etapes()->get() as $i => $e) {          // renumérotation continue
                $e->update(['ordre' => $i + 1]);
            }
        });

        return back()->with('success', 'Étape supprimée.');
    }

    /** Approbation par un autre utilisateur que l'auteur du circuit (séparation des tâches). */
    public function approuver(Circuit $circuit)
    {
        if ($circuit->statut !== 'BROUILLON') {
            throw new DomainException('Seul un circuit en brouillon peut être approuvé.');
        }
        if ($circuit->etapes()->doesntExist()) {
            throw new DomainException('Ajoutez au moins une étape avant d\'approuver le circuit.');
        }
        if ((int) $circuit->created_by === (int) Auth::id() && !Auth::user()->estSuperAdmin()) {
            throw new DomainException('Le circuit doit être approuvé par un autre utilisateur que son auteur.');
        }
        $circuit->update(['statut' => 'APPROUVE', 'approuve_par' => Auth::id(), 'approuve_at' => now()]);
        Audit::logSave(['action' => 'APPROUVER CIRCUIT', 'code_piece' => $circuit->id, 'menu' => 'CIRCUITS DE VALIDATION', 'etat' => 'Succès', 'objet' => 'VALIDATION']);

        return back()->with('success', 'Circuit approuvé : il peut être activé.');
    }

    /** Activation : la version précédente (même action et même filtre) est retirée. */
    public function activer(Circuit $circuit)
    {
        if ($circuit->statut !== 'APPROUVE') {
            throw new DomainException('Seul un circuit approuvé peut être activé.');
        }
        DB::transaction(function () use ($circuit) {
            Circuit::where('action', $circuit->action)->where('filtre', $circuit->filtre)->where('statut', 'ACTIF')->whereKeyNot($circuit->id)->update(['statut' => 'RETIRE']);
            $circuit->update(['statut' => 'ACTIF', 'active_at' => now()]);
        });
        Audit::logSave(['action' => 'ACTIVER CIRCUIT', 'code_piece' => $circuit->id, 'menu' => 'CIRCUITS DE VALIDATION', 'etat' => 'Succès', 'objet' => 'VALIDATION']);

        return back()->with('success', 'Circuit actif : l\'action « ' . Catalogue::libelle($circuit->action) . ' » est désormais soumise à validation.');
    }

    public function retirer(Circuit $circuit)
    {
        abort_unless(in_array($circuit->statut, ['ACTIF', 'APPROUVE'], true), 422);
        $circuit->update(['statut' => 'RETIRE']);

        return back()->with('success', 'Circuit retiré : l\'action s\'exécute de nouveau sans validation (les demandes en cours restent à décider).');
    }

    /** Nouvelle version (brouillon) d'un circuit, étapes comprises. */
    public function nouvelleVersion(Circuit $circuit)
    {
        $copie = DB::transaction(function () use ($circuit) {
            $copie = Circuit::create($circuit->only(['action', 'filtre', 'montant_declenchement', 'libelle', 'description']) + [
                'circuit_precedent_id' => $circuit->id, 'statut' => 'BROUILLON', 'created_by' => Auth::id(),
                'version' => (int) Circuit::where('action', $circuit->action)->where('filtre', $circuit->filtre)->max('version') + 1,
            ]);
            foreach ($circuit->etapes as $e) {
                $copie->etapes()->create($e->only(['ordre', 'libelle', 'role_id', 'permission_requise', 'user_id', 'montant_min', 'montant_max', 'separation_auteur', 'delai_heures']));
            }

            return $copie;
        });

        return redirect()->route('circuits.show', $copie)->with('success', 'Version ' . $copie->version . ' créée en brouillon.');
    }

    protected function modifiable(Circuit $circuit): void
    {
        if (!$circuit->estModifiable()) {
            throw new DomainException('Ce circuit n\'est plus un brouillon : créez une nouvelle version pour le modifier.');
        }
    }

    protected function valider(Request $request, ?Circuit $circuit = null): array
    {
        return $request->validate([
            'action' => ['required', Rule::in(array_keys(Catalogue::options()))],
            'filtre' => ['nullable', 'string', 'max:60'],
            'montant_declenchement' => ['nullable', 'numeric', 'min:0'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    protected function validerEtape(Request $request): array
    {
        $agence = app(\App\Support\AgenceCourante::class)->id();
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'role_id' => ['nullable', Rule::in(Role::visiblesPour()->pluck('id')->all())],
            'user_id' => ['nullable', Rule::in(User::deLAgence($agence)->pluck('id')->all())],
            'permission_requise' => ['nullable', 'exists:permissions,name'],
            'montant_min' => ['nullable', 'numeric', 'min:0'],
            'montant_max' => ['nullable', 'numeric', 'min:0', 'gte:montant_min'],
            'delai_heures' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'separation_auteur' => ['nullable', 'boolean'],
        ]);
        if (empty($donnees['role_id']) && empty($donnees['user_id']) && empty($donnees['permission_requise'])) {
            throw new DomainException('Désignez le validateur de l\'étape : un profil, un utilisateur ou une permission.');
        }

        return $donnees + ['separation_auteur' => $request->boolean('separation_auteur', true)];
    }
}
