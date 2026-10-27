<?php

namespace App\Http\Controllers\Syndic;

use App\Helpers\Audit;
use App\Http\Controllers\CrudController;
use App\Models\Bien;
use App\Models\Budget;
use App\Models\CleLot;
use App\Models\CleRepartition;
use App\Models\Copropriete;
use App\Models\CoproLot;
use App\Models\Immeuble;
use App\Models\Programme;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Services\Syndic\SyndicService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CoproprieteController extends CrudController
{
    protected string $modele = Copropriete::class;

    protected string $route = 'coproprietes';

    protected string $titre = 'Copropriétés';

    protected string $titreSingulier = 'Copropriété';

    protected string $module = 'Syndic';

    protected array $with = ['immeuble'];

    protected array $recherche = ['nom', 'adresse', 'immatriculation'];

    protected string $tri = 'nom';

    protected string $sens = 'asc';

    public function __construct(protected SyndicService $service)
    {
    }

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'nom', 'label' => 'Nom de la copropriété', 'requis' => true],
            ['nom' => 'immeuble_id', 'label' => 'Immeuble', 'type' => 'select', 'col' => 'col-md-3', 'defaut' => request('immeuble_id'),
                'options' => fn () => Immeuble::orderBy('nom')->pluck('nom', 'id')],
            ['nom' => 'programme_id', 'label' => 'Programme / résidence', 'type' => 'select', 'col' => 'col-md-3', 'options' => fn () => Programme::orderBy('nom')->pluck('nom', 'id')],
            ['nom' => 'adresse', 'label' => 'Adresse'],
            ['nom' => 'immatriculation', 'label' => 'Immatriculation / référence', 'col' => 'col-md-3'],
            ['nom' => 'total_tantiemes', 'label' => 'Total des tantièmes', 'type' => 'number', 'requis' => true, 'col' => 'col-md-3',
                'defaut' => parametre('syndic.total_tantiemes'), 'regles' => ['required', 'integer', 'min:1']],
            ['nom' => 'mandat_debut', 'label' => 'Début du mandat de syndic', 'type' => 'date', 'col' => 'col-md-3'],
            ['nom' => 'mandat_fin', 'label' => 'Fin du mandat', 'type' => 'date', 'col' => 'col-md-3', 'regles' => ['nullable', 'date', 'after:mandat_debut']],
            ['nom' => 'honoraires_annuels', 'label' => 'Honoraires annuels du syndic', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'taux_fonds_travaux', 'label' => 'Fonds de travaux (% du budget)', 'type' => 'number', 'col' => 'col-md-3',
                'defaut' => parametre('syndic.taux_fonds_travaux'), 'regles' => ['nullable', 'numeric', 'min:0', 'max:100']],
            ['nom' => 'exercice_debut_mois', 'label' => 'Mois de début d\'exercice', 'type' => 'select', 'col' => 'col-md-3', 'defaut' => 1, 'requis' => true,
                'options' => collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => ucfirst(now()->month($m)->locale('fr')->translatedFormat('F'))])->all()],
            ['nom' => 'banque', 'label' => 'Banque du syndicat', 'col' => 'col-md-3'],
            ['nom' => 'rib', 'label' => 'RIB', 'col' => 'col-md-6'],
            ['nom' => 'reglement', 'label' => 'Notes sur le règlement de copropriété', 'type' => 'textarea'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Copropriété', fn ($c) => Cellule::deuxLignes($c->nom, $c->adresse)],
            ['Immeuble', fn ($c) => $c->immeuble?->nom],
            ['Lots', fn ($c) => $c->lots_count, 'text-end'],
            ['Tantièmes', fn ($c) => $c->total_tantiemes, 'text-end'],
            ['Mandat', fn ($c) => $c->mandat_fin ? 'jusqu\'au ' . $c->mandat_fin->format('d/m/Y') : ''],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->withCount('lots');
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $donnees['honoraires_annuels'] ??= 0;
        $donnees['taux_fonds_travaux'] ??= 0;

        return $donnees;
    }

    public function show(string $id)
    {
        $copro = $this->trouver($id);
        $copro->load(['lots.coproprietaire', 'lots.bien', 'cles.lots', 'budgets.lignes', 'assemblees']);

        $situations = $copro->lots->pluck('coproprietaire')->filter()->unique('id')
            ->mapWithKeys(fn ($t) => [$t->id => $this->service->situation($copro, $t)]);

        return view('syndic.copropriete', [
            'copro' => $copro,
            'situations' => $situations,
            'tiers' => Tiers::orderBy('nom')->get()->pluck('nom_complet', 'id'),
            'biens' => $copro->immeuble_id ? Bien::where('immeuble_id', $copro->immeuble_id)->orderBy('reference')->get()->pluck('designation', 'id') : collect(),
            'postes' => Referentiel::options('categorie_depense'),
            'appels' => $copro->appels()->latest('date_appel')->limit(12)->get(),
        ]);
    }

    public function ajouterLot(Request $request, Copropriete $copropriete)
    {
        $this->service->ajouterLot($copropriete, $this->validerLot($request, $copropriete));

        return back()->with('success', 'Lot ajouté.');
    }

    public function modifierLot(Request $request, Copropriete $copropriete, CoproLot $lot)
    {
        abort_unless($lot->copropriete_id === $copropriete->id, 404);
        $lot->update($this->validerLot($request, $copropriete, $lot));
        $lot->coproprietaire?->ajouterRole('coproprietaire');

        return back()->with('success', 'Lot modifié.');
    }

    public function supprimerLot(Copropriete $copropriete, CoproLot $lot)
    {
        abort_unless($lot->copropriete_id === $copropriete->id, 404);
        if ($lot->appelLignes()->exists()) {
            return back()->with('error', 'Ce lot a déjà reçu des appels de fonds : il ne peut pas être supprimé.');
        }
        $lot->delete();

        return back()->with('success', 'Lot supprimé.');
    }

    public function ajouterCle(Request $request, Copropriete $copropriete)
    {
        $donnees = $request->validate(['libelle' => ['required', 'string', 'max:255']]);
        $copropriete->cles()->create(['libelle' => $donnees['libelle'], 'code' => Str::upper(Str::slug($donnees['libelle'], '_'))]);

        return back()->with('success', 'Clé de répartition créée : renseignez les tantièmes de chaque lot concerné.');
    }

    public function tantiemesCle(Request $request, Copropriete $copropriete, CleRepartition $cle)
    {
        abort_unless($cle->copropriete_id === $copropriete->id, 404);
        $donnees = $request->validate(['tantiemes' => ['array'], 'tantiemes.*' => ['nullable', 'numeric', 'min:0']]);

        foreach ($donnees['tantiemes'] ?? [] as $lotId => $valeur) {
            abort_unless($copropriete->lots()->whereKey($lotId)->exists(), 404);
            (float) $valeur > 0
                ? CleLot::updateOrCreate(['cle_repartition_id' => $cle->id, 'copro_lot_id' => $lotId], ['tantiemes' => $valeur])
                : CleLot::where(['cle_repartition_id' => $cle->id, 'copro_lot_id' => $lotId])->delete();
        }

        return back()->with('success', 'Tantièmes de la clé « ' . $cle->libelle . ' » enregistrés.');
    }

    public function enregistrerBudget(Request $request, Copropriete $copropriete)
    {
        $donnees = $request->validate([
            'exercice' => ['required', 'integer', 'min:2000', 'max:2100'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.poste' => ['required', Rule::in(Referentiel::options('categorie_depense')->keys())],
            'lignes.*.libelle' => ['nullable', 'string', 'max:255'],
            'lignes.*.cle_repartition_id' => ['nullable', Rule::exists('cles_repartition', 'id')->where('copropriete_id', $copropriete->id)],
            'lignes.*.montant' => ['nullable', 'numeric', 'min:0'],
        ]);

        $budget = $this->service->enregistrerBudget($copropriete, (int) $donnees['exercice'], $donnees['lignes']);

        return back()->with('success', 'Budget ' . $budget->exercice . ' enregistré : ' . montant($budget->montant_total) . '.');
    }

    public function voterBudget(Copropriete $copropriete, Budget $budget)
    {
        abort_unless($budget->copropriete_id === $copropriete->id, 404);
        $this->service->voterBudget($budget);

        return back()->with('success', 'Budget ' . $budget->exercice . ' voté.');
    }

    public function genererAppels(Request $request, Copropriete $copropriete, Budget $budget)
    {
        abort_unless($budget->copropriete_id === $copropriete->id, 404);
        $nombre = $request->validate(['nombre' => ['required', 'integer', Rule::in([1, 2, 3, 4, 6, 12])]])['nombre'];

        $appels = $this->service->genererAppelsProvision($budget, (int) $nombre);
        Audit::logSave(['action' => 'GENERER APPELS', 'code_piece' => $budget->id, 'menu' => 'COPROPRIETES', 'etat' => 'Succès', 'objet' => 'SYNDIC']);

        return redirect()->route('appelsfonds.index', ['copropriete_id' => $copropriete->id])
            ->with('success', count($appels) . ' appel(s) de fonds créé(s) en brouillon : émettez-les à leur date.');
    }

    protected function validerLot(Request $request, Copropriete $copropriete, ?CoproLot $lot = null): array
    {
        return $request->validate([
            'numero' => ['required', 'string', 'max:30', Rule::unique('copro_lots', 'numero')->where('copropriete_id', $copropriete->id)->ignore($lot?->id)],
            'designation' => ['nullable', 'string', 'max:255'],
            'tantiemes' => ['required', 'numeric', 'min:0'],
            'coproprietaire_id' => ['nullable', Rule::exists('tiers', 'id')->where('agence_id', agence()->id)],
            'occupant_id' => ['nullable', Rule::exists('tiers', 'id')->where('agence_id', agence()->id)],
            'bien_id' => ['nullable', Rule::exists('biens', 'id')->where('agence_id', agence()->id)],
        ]);
    }
}
