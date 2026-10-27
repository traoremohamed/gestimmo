<?php

namespace App\Http\Controllers\Rh;

use App\Helpers\Audit;
use App\Http\Controllers\CrudController;
use App\Models\Pays;
use App\Models\Referentiel;
use App\Models\Rh\Contrat;
use App\Models\Rh\Employe;
use App\Models\Rh\Historique;
use App\Models\User;
use App\Services\Rh\RhService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EmployeController extends CrudController
{
    protected string $modele = Employe::class;

    protected string $route = 'employes';

    protected string $titre = 'Personnel';

    protected string $titreSingulier = 'Employé';

    protected string $module = 'RH & personnel';

    protected array $with = ['contratActif'];

    protected array $recherche = ['nom', 'prenoms', 'matricule', 'telephone', 'email'];

    protected string $tri = 'nom';

    protected string $sens = 'asc';

    protected ?string $vueFiche = 'rh.employe';

    public function __construct(protected RhService $service)
    {
    }

    protected function champs(?Model $element = null): array
    {
        $champs = [
            ['nom' => '_t1', 'label' => 'Identité', 'type' => 'titre'],
            ['nom' => 'matricule', 'label' => 'Matricule', 'col' => 'col-md-3', 'aide' => 'Vide = automatique',
                'regles' => ['nullable', 'string', 'max:30', Rule::unique('rh_employes', 'matricule')->where('agence_id', agence()?->id)->ignore($element?->id)]],
            ['nom' => 'civilite', 'label' => 'Civilité', 'type' => 'select', 'options' => ['M.' => 'M.', 'Mme' => 'Mme', 'Mlle' => 'Mlle'], 'col' => 'col-md-2'],
            ['nom' => 'nom', 'label' => 'Nom', 'requis' => true, 'col' => 'col-md-3'],
            ['nom' => 'prenoms', 'label' => 'Prénoms', 'col' => 'col-md-4'],
            ['nom' => 'sexe', 'label' => 'Sexe', 'type' => 'select', 'options' => ['M' => 'Masculin', 'F' => 'Féminin'], 'col' => 'col-md-2'],
            ['nom' => 'date_naissance', 'label' => 'Date de naissance', 'type' => 'date', 'col' => 'col-md-3', 'regles' => ['nullable', 'date', 'before:today']],
            ['nom' => 'lieu_naissance', 'label' => 'Lieu de naissance', 'col' => 'col-md-3'],
            ['nom' => 'nationalite_id', 'label' => 'Nationalité', 'type' => 'select', 'options' => fn () => Pays::orderBy('nationalite')->pluck('nationalite', 'id'), 'col' => 'col-md-4', 'recherche' => true],
            ['nom' => 'piece_identite', 'label' => 'N° pièce d\'identité', 'col' => 'col-md-4'],
            ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'col' => 'col-md-4'],
            ['nom' => 'email', 'label' => 'E-mail', 'type' => 'email', 'col' => 'col-md-4'],
            ['nom' => 'adresse', 'label' => 'Adresse', 'col' => 'col-md-8'],
            ['nom' => 'contact_urgence', 'label' => 'Contact d\'urgence', 'col' => 'col-md-4'],

            ['nom' => '_t2', 'label' => 'Poste et situation', 'type' => 'titre'],
            ['nom' => 'departement', 'label' => 'Département', 'type' => 'select', 'options' => fn () => Referentiel::options('departement'), 'col' => 'col-md-4'],
            ['nom' => 'poste', 'label' => 'Poste', 'type' => 'select', 'options' => fn () => Referentiel::options('poste'), 'col' => 'col-md-4'],
            ['nom' => 'date_embauche', 'label' => 'Date d\'embauche', 'type' => 'date', 'requis' => true, 'col' => 'col-md-4', 'defaut' => today()],
            ['nom' => 'situation_familiale', 'label' => 'Situation familiale', 'type' => 'select', 'options' => Employe::SITUATIONS, 'col' => 'col-md-3'],
            ['nom' => 'nombre_enfants', 'label' => 'Enfants à charge', 'type' => 'number', 'col' => 'col-md-3', 'defaut' => 0, 'regles' => ['nullable', 'integer', 'min:0', 'max:30']],
            ['nom' => 'nombre_parts', 'label' => 'Parts fiscales (ITS)', 'type' => 'number', 'col' => 'col-md-3', 'aide' => 'Vide = calcul automatique', 'regles' => ['nullable', 'numeric', 'min:1', 'max:5']],
            ['nom' => 'numero_cnps', 'label' => 'N° CNPS', 'col' => 'col-md-3'],
            ['nom' => 'banque', 'label' => 'Banque', 'col' => 'col-md-4'],
            ['nom' => 'rib', 'label' => 'RIB / compte', 'col' => 'col-md-4'],
            ['nom' => 'user_id', 'label' => 'Compte utilisateur (commissions, agenda)', 'type' => 'select', 'col' => 'col-md-4',
                'options' => fn () => User::where('agence_id', agence()?->id)->whereNotIn('id', Employe::whereNotNull('user_id')->when($element, fn ($q) => $q->whereKeyNot($element->id))->select('user_id'))
                    ->orderBy('name')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'solde_conges', 'label' => 'Solde de congés (jours)', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'min:0']],
            ['nom' => 'observations', 'label' => 'Observations', 'type' => 'textarea'],
        ];

        if (!$element) {
            // à l'embauche : le premier contrat
            array_push($champs,
                ['nom' => '_t3', 'label' => 'Contrat de travail', 'type' => 'titre'],
                ['nom' => 'type_contrat', 'label' => 'Type de contrat', 'type' => 'select', 'options' => Contrat::TYPES, 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'CDI'],
                ['nom' => 'date_fin', 'label' => 'Fin (CDD, stage)', 'type' => 'date', 'col' => 'col-md-3', 'regles' => ['nullable', 'date', 'after:date_embauche']],
                ['nom' => 'mode_remuneration', 'label' => 'Rémunération', 'type' => 'select', 'options' => Contrat::MODES, 'requis' => true, 'col' => 'col-md-6', 'defaut' => 'FIXE'],
                ['nom' => 'salaire_base', 'label' => 'Salaire de base mensuel', 'type' => 'montant', 'col' => 'col-md-3'],
                ['nom' => 'sursalaire', 'label' => 'Sursalaire', 'type' => 'montant', 'col' => 'col-md-3'],
                ['nom' => 'taux_journalier', 'label' => 'Taux journalier', 'type' => 'montant', 'col' => 'col-md-3'],
                ['nom' => 'taux_commission', 'label' => 'Commission (% des ventes)', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'min:0', 'max:100']],
                ['nom' => 'categorie', 'label' => 'Catégorie (convention)', 'col' => 'col-md-3'],
                ['nom' => 'mode_paiement', 'label' => 'Paiement du salaire', 'type' => 'select', 'options' => fn () => Referentiel::options('mode_paiement'), 'col' => 'col-md-3', 'defaut' => 'VIREMENT'],
                ['nom' => 'soumis_cotisations', 'label' => 'Déclaré à la CNPS', 'type' => 'checkbox', 'col' => 'col-md-3', 'defaut' => true],
                ['nom' => 'soumis_impot', 'label' => 'Soumis à l\'ITS', 'type' => 'checkbox', 'col' => 'col-md-3', 'defaut' => true],
            );
        }

        return $champs;
    }

    protected function colonnes(): array
    {
        return [
            ['Employé', fn ($e) => Cellule::lien($e->nom_complet, route('employes.show', $e))],
            ['Matricule', fn ($e) => $e->matricule],
            ['Poste', fn ($e) => Cellule::deuxLignes(Referentiel::libelle('poste', $e->poste), Referentiel::libelle('departement', $e->departement))],
            ['Contrat', fn ($e) => $e->contratActif ? $e->contratActif->type_contrat . ' — ' . montant($e->contratActif->salaire_base) : '—'],
            ['Embauche', fn ($e) => $e->date_embauche->format('d/m/Y')],
            ['Congés', fn ($e) => (float) $e->solde_conges . ' j', 'text-end'],
            ['Statut', fn ($e) => Cellule::badge(Employe::STATUTS[$e->statut][0], Employe::STATUTS[$e->statut][1])],
        ];
    }

    protected function filtres(): array
    {
        return [
            ['nom' => 'departement', 'label' => 'Département', 'options' => fn () => Referentiel::options('departement')],
            ['nom' => 'statut', 'label' => 'Statut', 'options' => collect(Employe::STATUTS)->map(fn ($s) => $s[0])->all()],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->when($request->filled('departement'), fn ($q) => $q->where('departement', $request->departement))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut), fn ($q) => $q->where('statut', '!=', 'sorti'));
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $champsContrat = ['type_contrat', 'date_fin', 'mode_remuneration', 'salaire_base', 'sursalaire', 'taux_journalier', 'taux_commission', 'categorie',
            'mode_paiement', 'soumis_cotisations', 'soumis_impot'];
        $contrat = array_intersect_key($donnees, array_flip($champsContrat));
        $employe = array_diff_key($donnees, $contrat);

        $e = $this->service->embaucher($employe, array_map(fn ($v) => $v ?? 0, $contrat) + ['date_debut' => $employe['date_embauche']]);
        $this->auditer('CREER', $e);

        return redirect()->route('employes.show', $e)->with('success', 'Employé ' . $e->nom_complet . ' (' . $e->matricule . ') enregistré.');
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        if (array_key_exists('nombre_parts', $donnees) && !$donnees['nombre_parts']) {
            $donnees['nombre_parts'] = Employe::partsFiscales($donnees['situation_familiale'] ?? null, (int) ($donnees['nombre_enfants'] ?? 0));
        }
        $donnees['nombre_enfants'] = (int) ($donnees['nombre_enfants'] ?? 0);
        $donnees['solde_conges'] = (float) ($donnees['solde_conges'] ?? 0);

        return $donnees;
    }

    public function show(string $id)
    {
        $employe = $this->trouver($id);
        $employe->load(['contrats', 'historique.auteur', 'absences', 'avances', 'bulletins.periode', 'compte']);

        return view('rh.employe', $this->donneesVue() + ['employe' => $employe]);
    }

    public function nouveauContrat(Request $request, Employe $employe)
    {
        $donnees = $request->validate([
            'type_contrat' => ['required', Rule::in(array_keys(Contrat::TYPES))],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after:date_debut'],
            'mode_remuneration' => ['required', Rule::in(array_keys(Contrat::MODES))],
            'salaire_base' => ['nullable', 'numeric', 'min:0'],
            'sursalaire' => ['nullable', 'numeric', 'min:0'],
            'taux_journalier' => ['nullable', 'numeric', 'min:0'],
            'taux_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'categorie' => ['nullable', 'string', 'max:20'],
            'mode_paiement' => ['required', Rule::in(Referentiel::options('mode_paiement')->keys())],
            'motif' => ['required', Rule::in(['augmentation', 'promotion', 'mutation'])],
        ]);
        $motif = $donnees['motif'];
        unset($donnees['motif']);

        $this->service->nouveauContrat($employe, array_map(fn ($v) => $v ?? 0, $donnees) + ['soumis_cotisations' => true, 'soumis_impot' => true], $motif,
            Historique::TYPES[$motif] . ' — ' . Contrat::TYPES[$donnees['type_contrat']] . ', ' . montant($donnees['salaire_base'] ?? 0));

        return back()->with('success', 'Nouveau contrat enregistré, l\'ancien est clos.');
    }

    public function evenement(Request $request, Employe $employe)
    {
        $donnees = $request->validate([
            'type' => ['required', Rule::in(['formation', 'evaluation', 'sanction', 'mutation', 'promotion'])],
            'date_effet' => ['required', 'date'],
            'libelle' => ['required', 'string', 'max:255'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $employe->historique()->create(['type' => $donnees['type'], 'date_effet' => $donnees['date_effet'], 'libelle' => $donnees['libelle'],
            'details' => array_filter(['commentaire' => $donnees['commentaire'] ?? null, 'note' => $donnees['note'] ?? null]), 'user_id' => $request->user()->id]);

        return back()->with('success', 'Événement ajouté au dossier.');
    }

    public function sortie(Request $request, Employe $employe)
    {
        $donnees = $request->validate(['date_sortie' => ['required', 'date'], 'motif_sortie' => ['required', 'string', 'max:255']]);
        $this->service->sortie($employe, Carbon::parse($donnees['date_sortie']), $donnees['motif_sortie']);
        Audit::logSave(['action' => 'SORTIE', 'code_piece' => $employe->matricule, 'menu' => 'PERSONNEL', 'etat' => 'Succès', 'objet' => 'RH']);

        return back()->with('success', 'Sortie enregistrée.');
    }

    protected function supprimable(Model $element): bool
    {
        return !$element->bulletins()->exists();
    }
}
