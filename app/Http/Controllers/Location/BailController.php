<?php

namespace App\Http\Controllers\Location;

use App\Enums\StatutBail;
use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Bail;
use App\Models\Bien;
use App\Models\EtatLieux;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Services\Location\BailService;
use App\Support\Pdf;
use App\Services\Ged\GedService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BailController extends Controller
{
    public function __construct(protected BailService $service)
    {
    }

    public function index(Request $request)
    {
        $baux = Bail::with(['bien', 'locataire'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('numero', 'ilike', '%' . $request->q . '%')
                ->orWhereHas('locataire', fn ($t) => $t->recherche($request->q))
                ->orWhereHas('bien', fn ($b) => $b->where('reference', 'ilike', '%' . $request->q . '%'))))
            ->latest()
            ->paginate(25)->withQueryString();

        return view('location.baux', compact('baux'));
    }

    public function create(Request $request)
    {
        return view('location.bail_form', $this->donneesFormulaire() + [
            'bail' => null,
            'bienId' => $request->query('bien_id'),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $bail = $this->service->creer(Bien::findOrFail($donnees['bien_id']), Tiers::findOrFail($donnees['locataire_id']), $donnees);

        return redirect()->route('baux.show', $bail)->with('success', 'Bail enregistré en brouillon. Vérifiez les conditions puis activez-le.');
    }

    public function show(Bail $bail)
    {
        $bail->load(['bien.proprietaire', 'locataire', 'garant', 'mandat', 'etatsLieux.agent', 'decomptes']);

        return view('location.bail', [
            'bail' => $bail,
            'loyers' => $bail->loyers()->orderBy('date_echeance')->orderBy('id')->get(),
            'motifs' => Referentiel::options('motif_resiliation'),
        ]);
    }

    public function edit(Bail $bail)
    {
        abort_unless($bail->statut === StatutBail::Brouillon, 403, 'Un bail actif ne se modifie pas : résiliez-le ou révisez le loyer.');

        return view('location.bail_form', $this->donneesFormulaire() + ['bail' => $bail, 'bienId' => $bail->bien_id]);
    }

    public function update(Request $request, Bail $bail)
    {
        abort_unless($bail->statut === StatutBail::Brouillon, 403);
        $donnees = $this->valider($request);
        $donnees['date_fin'] = Carbon::parse($donnees['date_debut'])->addMonthsNoOverflow((int) $donnees['duree_mois'])->subDay();
        $bail->update($donnees);

        return redirect()->route('baux.show', $bail)->with('success', 'Bail modifié.');
    }

    public function activer(Bail $bail)
    {
        if ($attente = $this->soumettre('BAIL_ACTIVATION', $bail, filtre: $bail->type_bail)) {
            return $attente;
        }
        $this->service->activer($bail);
        Audit::logSave(['action' => 'ACTIVER', 'code_piece' => $bail->numero, 'menu' => 'BAUX', 'etat' => 'Succès', 'objet' => 'GESTION LOCATIVE']);

        return back()->with('success', 'Bail ' . $bail->numero . ' activé : dépôt de garantie, frais et loyers d\'avance générés.');
    }

    public function resilier(Request $request, Bail $bail)
    {
        $donnees = $request->validate([
            'date_resiliation' => ['required', 'date', 'after_or_equal:' . $bail->date_debut->toDateString()],
            'motif' => ['required', Rule::in(Referentiel::options('motif_resiliation')->keys())],
            'depot_restitue' => ['nullable', 'numeric', 'min:0', 'max:' . (float) $bail->depot_garantie],
        ]);

        if ($attente = $this->soumettre('BAIL_RESILIATION', $bail, $donnees, filtre: $donnees['motif'])) {
            return $attente;
        }
        $this->service->resilier($bail, Carbon::parse($donnees['date_resiliation']), $donnees['motif'], $donnees['depot_restitue'] ?? null);
        Audit::logSave(['action' => 'RESILIER', 'code_piece' => $bail->numero, 'menu' => 'BAUX', 'etat' => 'Succès', 'objet' => 'GESTION LOCATIVE']);

        return back()->with('success', 'Bail résilié, le bien est de nouveau disponible.');
    }

    public function reviser(Request $request, Bail $bail)
    {
        $donnees = $request->validate(['loyer_mensuel' => ['required', 'numeric', 'min:0']]);
        $this->service->reviser($bail, $donnees['loyer_mensuel']);

        return back()->with('success', 'Loyer révisé : il s\'appliquera aux prochaines échéances générées.');
    }

    public function etatLieux(Request $request, Bail $bail)
    {
        $donnees = $request->validate([
            'type' => ['required', Rule::in(['entree', 'sortie'])],
            'date' => ['required', 'date'],
            'releves_compteurs' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:5000'],
            'pieces' => ['nullable', 'array'],
            'pieces.*.piece' => ['nullable', 'string', 'max:100'],
            'pieces.*.etat' => ['nullable', Rule::in(array_keys(EtatLieux::ETATS))],
            'pieces.*.commentaire' => ['nullable', 'string', 'max:500'],
            'compteurs.cie_index' => ['nullable', 'string', 'max:40'],
            'compteurs.sodeci_index' => ['nullable', 'string', 'max:40'],
            'reparations' => ['nullable', 'array'],
            'reparations.*.libelle' => ['nullable', 'string', 'max:255'],
            'reparations.*.montant' => ['nullable', 'numeric', 'min:0'],
            'reparations.*.a_charge' => ['nullable', Rule::in(['locataire', 'proprietaire'])],
            'photos' => ['nullable', 'array', 'max:30'],
            'photos.*' => ['file'],
        ]);

        $donnees['pieces'] = array_values(array_filter($donnees['pieces'] ?? [], fn ($p) => !empty($p['piece'])));
        $donnees['reparations'] = array_values(array_filter($donnees['reparations'] ?? [], fn ($r) => !empty($r['libelle']) && (float) ($r['montant'] ?? 0) > 0));
        $etat = $bail->etatsLieux()->create(Arr::except($donnees, ['photos']) + ['user_id' => $request->user()->id]);

        // photos et pièces jointes de l'état des lieux : archivées dans la GED
        foreach ($request->file('photos', []) as $photo) {
            app(GedService::class)->televerser($photo, ['titre' => 'État des lieux ' . ($etat->type === 'entree' ? "d'entrée" : 'de sortie') . ' — ' . $bail->numero,
                'categorie' => 'ETAT_LIEUX'], $etat);
        }

        return back()->with('success', 'État des lieux enregistré.' . ($etat->type === 'sortie' && $etat->reparations ? ' Les réparations à la charge du locataire seront reprises dans le décompte de sortie.' : ''));
    }

    public function contrat(Bail $bail)
    {
        abort_if($bail->statut === StatutBail::Brouillon, 404);
        $bail->load(['bien.immeuble', 'bien.proprietaire', 'locataire.nationalite', 'garant']);

        return Pdf::telecharger('pdf.contrat_bail', ['bail' => $bail], 'bail-' . $bail->numero, objet: $bail, type: 'CONTRAT_BAIL',
            etat: md5($bail->loyer_mensuel . '|' . $bail->charges_mensuelles . '|' . $bail->locataire_id . '|' . $bail->date_debut?->toDateString()));
    }

    protected function valider(Request $request): array
    {
        $agence = agence()->id;

        return $request->validate([
            'bien_id' => ['required', Rule::exists('biens', 'id')->where('agence_id', $agence)],
            'locataire_id' => ['required', Rule::exists('tiers', 'id')->where('agence_id', $agence)],
            'garant_id' => ['nullable', 'different:locataire_id', Rule::exists('tiers', 'id')->where('agence_id', $agence)],
            'type_bail' => ['required', Rule::in(array_keys(Bail::TYPES))],
            'date_debut' => ['required', 'date'],
            'duree_mois' => ['required', 'integer', 'min:1', 'max:240'],
            'tacite_reconduction' => ['boolean'],
            'loyer_mensuel' => ['required', 'numeric', 'min:0'],
            'charges_mensuelles' => ['nullable', 'numeric', 'min:0'],
            'periodicite_mois' => ['required', Rule::in([1, 3, 6, 12])],
            'jour_echeance' => ['required', 'integer', 'min:1', 'max:28'],
            'mois_caution' => ['required', 'integer', 'min:0', 'max:24'],
            'mois_avance' => ['required', 'integer', 'min:0', 'max:24'],
            'depot_garantie' => ['nullable', 'numeric', 'min:0'],
            'caution_sodeci' => ['nullable', 'numeric', 'min:0'],
            'caution_cie' => ['nullable', 'numeric', 'min:0'],
            'compteur_sodeci_numero' => ['nullable', 'string', 'max:40'],
            'compteur_cie_numero' => ['nullable', 'string', 'max:40'],
            'compteur_cie_credit' => ['boolean'],
            'compteur_cie_montant_credit' => ['nullable', 'numeric', 'min:0'],
            'frais_agence' => ['nullable', 'numeric', 'min:0'],
            'taux_penalite' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taux_revision' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'date_prochaine_revision' => ['nullable', 'date'],
            'observation' => ['nullable', 'string', 'max:5000'],
        ]) + ['tacite_reconduction' => $request->boolean('tacite_reconduction'), 'compteur_cie_credit' => $request->boolean('compteur_cie_credit'),
            'caution_sodeci' => $request->input('caution_sodeci') ?: 0, 'caution_cie' => $request->input('caution_cie') ?: 0,
            'compteur_cie_montant_credit' => $request->input('compteur_cie_montant_credit') ?: 0];
    }

    protected function donneesFormulaire(): array
    {
        return [
            'biens' => Bien::where('a_louer', true)->orderBy('reference')->get(),
            'tiers' => Tiers::orderBy('nom')->get()->pluck('nom_complet', 'id'),
        ];
    }
}
