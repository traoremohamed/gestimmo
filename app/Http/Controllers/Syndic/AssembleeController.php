<?php

namespace App\Http\Controllers\Syndic;

use App\Enums\StatutAssemblee;
use App\Http\Controllers\CrudController;
use App\Models\Assemblee;
use App\Models\Copropriete;
use App\Models\Resolution;
use App\Services\Syndic\SyndicService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssembleeController extends CrudController
{
    protected string $modele = Assemblee::class;

    protected string $route = 'assemblees';

    protected string $titre = 'Assemblées générales';

    protected string $titreSingulier = 'Assemblée';

    protected string $module = 'Syndic';

    protected array $with = ['copropriete'];

    protected string $tri = 'date_heure';

    protected ?string $vueFiche = 'syndic.assemblee';

    public function __construct(protected SyndicService $service)
    {
    }

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => 'copropriete_id', 'label' => 'Copropriété', 'type' => 'select', 'requis' => true, 'defaut' => request('copropriete_id'),
                'options' => fn () => Copropriete::orderBy('nom')->pluck('nom', 'id')],
            ['nom' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => Assemblee::TYPES, 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'ordinaire'],
            ['nom' => 'date_heure', 'label' => 'Date et heure', 'type' => 'datetime', 'requis' => true, 'col' => 'col-md-3'],
            ['nom' => 'lieu', 'label' => 'Lieu'],
            ['nom' => 'ordre_du_jour', 'label' => 'Ordre du jour', 'type' => 'textarea', 'rows' => 5],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Date', fn ($a) => $a->date_heure->format('d/m/Y H:i')],
            ['Copropriété', fn ($a) => $a->copropriete?->nom],
            ['Type', fn ($a) => Assemblee::TYPES[$a->type] ?? $a->type],
            ['Lieu', fn ($a) => $a->lieu],
            ['Statut', fn ($a) => Cellule::statut($a->statut)],
        ];
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        return $element ? $donnees : $donnees + ['statut' => StatutAssemblee::Planifiee];
    }

    public function show(string $id)
    {
        $assemblee = $this->trouver($id);
        $assemblee->load(['resolutions', 'copropriete']);

        return view('syndic.assemblee', $this->donneesVue() + ['assemblee' => $assemblee]);
    }

    public function convoquer(Assemblee $assemblee)
    {
        $this->service->convoquer($assemblee);

        return back()->with('success', 'Assemblée convoquée.');
    }

    public function ajouterResolution(Request $request, Assemblee $assemblee)
    {
        $donnees = $request->validate([
            'intitule' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'majorite' => ['required', Rule::in(array_keys(Resolution::MAJORITES))],
        ]);

        $assemblee->resolutions()->create($donnees + ['numero' => ($assemblee->resolutions()->max('numero') ?? 0) + 1]);

        return back()->with('success', 'Résolution ajoutée à l\'ordre du jour.');
    }

    public function voter(Request $request, Assemblee $assemblee, Resolution $resolution)
    {
        abort_unless($resolution->assemblee_id === $assemblee->id, 404);
        $donnees = $request->validate([
            'voix_pour' => ['required', 'numeric', 'min:0'],
            'voix_contre' => ['required', 'numeric', 'min:0'],
            'voix_abstention' => ['nullable', 'numeric', 'min:0'],
        ]);

        $resolution = $this->service->voter($resolution, $donnees['voix_pour'], $donnees['voix_contre'], $donnees['voix_abstention'] ?? 0);

        return back()->with('success', 'Résolution n° ' . $resolution->numero . ($resolution->adoptee ? ' adoptée.' : ' rejetée.'));
    }

    public function cloturer(Request $request, Assemblee $assemblee)
    {
        $donnees = $request->validate([
            'tantiemes_presents' => ['required', 'numeric', 'min:0', 'max:' . $assemblee->copropriete->total_tantiemes],
            'proces_verbal' => ['nullable', 'string', 'max:20000'],
        ]);

        $assemblee->update($donnees + ['statut' => StatutAssemblee::Tenue]);

        return back()->with('success', 'Assemblée clôturée, procès-verbal enregistré.');
    }
}
