<?php

namespace App\Http\Controllers\Maintenance;

use App\Enums\StatutIntervention;
use App\Http\Controllers\CrudController;
use App\Models\Bien;
use App\Models\Copropriete;
use App\Models\Intervention;
use App\Models\Reclamation;
use App\Models\Referentiel;
use App\Models\Reclamation as ReclamationModel;
use App\Models\Tiers;
use App\Services\Maintenance\InterventionService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InterventionController extends CrudController
{
    protected string $modele = Intervention::class;

    protected string $route = 'interventions';

    protected string $titre = 'Interventions & travaux';

    protected string $titreSingulier = 'Intervention';

    protected string $module = 'Interventions';

    protected array $with = ['bien', 'copropriete', 'fournisseur'];

    protected array $recherche = ['numero', 'objet'];

    protected ?string $vueFiche = 'maintenance.intervention';

    public function __construct(protected InterventionService $service)
    {
    }

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'objet', 'label' => 'Objet', 'requis' => true, 'col' => 'col-md-8'],
            ['nom' => 'priorite', 'label' => 'Priorité', 'type' => 'select', 'options' => ReclamationModel::PRIORITES, 'requis' => true, 'col' => 'col-md-4', 'defaut' => 'normale'],
            ['nom' => 'bien_id', 'label' => 'Bien', 'type' => 'select', 'recherche' => true, 'col' => 'col-md-6', 'defaut' => request('bien_id'),
                'options' => fn () => Bien::orderBy('reference')->get()->pluck('designation', 'id')],
            ['nom' => 'copropriete_id', 'label' => 'Ou parties communes de la copropriété', 'type' => 'select', 'col' => 'col-md-6',
                'options' => fn () => Copropriete::orderBy('nom')->pluck('nom', 'id')],
            ['nom' => 'reclamation_id', 'label' => 'Réclamation d\'origine', 'type' => 'select', 'col' => 'col-md-6', 'defaut' => request('reclamation_id'),
                'options' => fn () => Reclamation::whereIn('statut', ['ouverte', 'en_cours'])->latest()->get()->mapWithKeys(fn ($r) => [$r->id => $r->objet . ' (' . $r->created_at->format('d/m/Y') . ')'])],
            ['nom' => 'fournisseur_id', 'label' => 'Prestataire', 'type' => 'select', 'recherche' => true, 'col' => 'col-md-6',
                'options' => fn () => Tiers::avecRole('fournisseur')->orderBy('nom')->get()->mapWithKeys(fn ($t) => [$t->id => $t->nom_complet . ($t->corps_metier ? ' — ' . Referentiel::libelle('corps_metier', $t->corps_metier) : '')])],
            ['nom' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => collect(StatutIntervention::options())->except('terminee')->all(), 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'signalee'],
            ['nom' => 'date_prevue', 'label' => 'Date prévue', 'type' => 'date', 'col' => 'col-md-3'],
            ['nom' => 'cout_estime', 'label' => 'Coût estimé', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'a_charge_de', 'label' => 'À la charge de', 'type' => 'select', 'options' => Intervention::A_CHARGE_DE, 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'proprietaire'],
            ['nom' => 'description', 'label' => 'Description', 'type' => 'textarea'],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Intervention', fn ($i) => Cellule::deuxLignes($i->numero, $i->date_signalement->format('d/m/Y'))],
            ['Objet', fn ($i) => $i->objet],
            ['Lieu', fn ($i) => $i->bien?->reference ?? $i->copropriete?->nom],
            ['Prestataire', fn ($i) => $i->fournisseur?->nom_complet],
            ['Priorité', fn ($i) => Cellule::badge(ReclamationModel::PRIORITES[$i->priorite] ?? $i->priorite, ['urgente' => 'danger', 'haute' => 'warning'][$i->priorite] ?? 'light text-dark')],
            ['Coût', fn ($i) => Cellule::montant($i->cout_reel ?? $i->cout_estime ?? 0), 'text-end'],
            ['Statut', fn ($i) => Cellule::statut($i->statut)],
        ];
    }

    protected function filtres(): array
    {
        return [
            ['nom' => 'statut', 'label' => 'Statut', 'options' => StatutIntervention::options()],
            ['nom' => 'copropriete_id', 'label' => 'Copropriété', 'options' => fn () => Copropriete::orderBy('nom')->pluck('nom', 'id')],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('copropriete_id'), fn ($q) => $q->where('copropriete_id', $request->copropriete_id));
    }

    public function store(Request $request)
    {
        $intervention = $this->service->creer($this->valider($request));
        $intervention->reclamation?->update(['statut' => 'en_cours']);
        $this->auditer('CREER', $intervention);

        return redirect()->route('interventions.show', $intervention)->with('success', 'Intervention ' . $intervention->numero . ' créée.');
    }

    public function terminer(Request $request, Intervention $intervention)
    {
        $donnees = $request->validate([
            'cout_reel' => ['nullable', 'numeric', 'min:0'],
            'date_realisation' => ['required', 'date'],
            'mode_paiement' => ['nullable', Rule::in(Referentiel::options('mode_paiement')->keys())],
        ]);

        $this->service->terminer($intervention, $donnees['cout_reel'] ?? null, \Illuminate\Support\Carbon::parse($donnees['date_realisation']), $donnees['mode_paiement'] ?? null);

        return back()->with('success', 'Intervention clôturée' . ($intervention->depense_id ? ' : dépense enregistrée.' : '.'));
    }
}
