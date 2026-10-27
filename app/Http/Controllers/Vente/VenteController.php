<?php

namespace App\Http\Controllers\Vente;

use App\Enums\StatutVente;
use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\FraisType;
use App\Models\Referentiel;
use App\Models\Reservation;
use App\Models\Tiers;
use App\Models\User;
use App\Models\Vente;
use App\Models\VenteFrais;
use App\Services\Vente\VenteService;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contrats de vente : un écran unique de montage (biens, frais, conditions, échéancier
 * simulé), puis validation, suivi des paiements, désistement et remboursement.
 */
class VenteController extends Controller
{
    public function __construct(protected VenteService $service)
    {
    }

    public function index(Request $request)
    {
        $ventes = Vente::with(['acquereur', 'commercial', 'biens'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('numero', 'ilike', '%' . $request->q . '%')
                ->orWhereHas('acquereur', fn ($t) => $t->recherche($request->q))))
            ->latest()
            ->paginate(25)->withQueryString();

        return view('vente.index', compact('ventes'));
    }

    public function create(Request $request)
    {
        return view('vente.create', [
            'tiers' => Tiers::orderBy('nom')->get(),
            'acquereurId' => $request->query('acquereur_id'),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate(['acquereur_id' => ['required', Rule::exists('tiers', 'id')->where('agence_id', agence()->id)]]);

        $vente = $this->service->creer(Tiers::findOrFail($donnees['acquereur_id']), $request->user());

        return redirect()->route('ventes.show', $vente)->with('success', 'Brouillon de contrat créé : ajoutez les biens et les conditions.');
    }

    public function show(Vente $vente)
    {
        $vente->load(['acquereur', 'commercial', 'lignes.bien', 'frais.fraisType', 'echeances', 'validePar']);

        return view('vente.show', [
            'vente' => $vente,
            'simulation' => $vente->estModifiable() ? $this->service->simulerEcheancier($vente) : [],
            'biensDisponibles' => $vente->estModifiable()
                ? Bien::where('a_vendre', true)->where(fn ($q) => $q->where('statut', 'disponible')
                    ->orWhereIn('id', Reservation::where('tiers_id', $vente->acquereur_id)->where('statut', 'active')->select('bien_id')))
                    ->whereNotIn('id', $vente->lignes->pluck('bien_id'))->orderBy('reference')->get()
                : collect(),
            'fraisTypes' => FraisType::activite('vente')->whereNotIn('id', $vente->frais->pluck('frais_type_id')->filter())->get(),
            'commerciaux' => User::where('agence_id', agence()->id)->orderBy('name')->get()->pluck('nom_complet', 'id'),
            'motifs' => Referentiel::options('motif_desistement'),
            'modes' => Referentiel::options('mode_paiement'),
        ]);
    }

    public function ajouterBien(Request $request, Vente $vente)
    {
        $donnees = $request->validate([
            'bien_id' => ['required', Rule::exists('biens', 'id')->where('agence_id', agence()->id)],
            'prix' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->service->ajouterBien($vente, Bien::findOrFail($donnees['bien_id']), $donnees['prix'] ?? null);

        return back()->with('success', 'Bien ajouté au contrat.');
    }

    public function retirerBien(Vente $vente, Bien $bien)
    {
        $this->service->retirerBien($vente, $bien);

        return back()->with('success', 'Bien retiré du contrat.');
    }

    public function ajouterFrais(Request $request, Vente $vente)
    {
        $donnees = $request->validate([
            'frais_type_id' => ['required', Rule::exists('frais_types', 'id')->where('agence_id', agence()->id)],
            'montant' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->service->ajouterFrais($vente, FraisType::findOrFail($donnees['frais_type_id']), $donnees['montant'] ?? null);

        return back()->with('success', 'Frais ajouté.');
    }

    public function retirerFrais(Vente $vente, VenteFrais $frais)
    {
        abort_unless($frais->vente_id === $vente->id, 404);
        $this->service->retirerFrais($frais);

        return back()->with('success', 'Frais retiré.');
    }

    public function conditions(Request $request, Vente $vente)
    {
        $donnees = $request->validate([
            'date_contrat' => ['required', 'date'],
            'commercial_id' => ['nullable', Rule::exists('users', 'id')->where('agence_id', agence()->id)],
            'remise' => ['nullable', 'numeric', 'min:0'],
            'mode_financement' => ['required', Rule::in(array_keys(Vente::MODES_FINANCEMENT))],
            'taux_acompte' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'montant_acompte' => ['nullable', 'numeric', 'min:0'],
            'nombre_echeances' => ['required_unless:mode_financement,comptant', 'nullable', 'integer', 'min:1', 'max:360'],
            'periodicite_mois' => ['required_unless:mode_financement,comptant', 'nullable', 'integer', 'min:1', 'max:12'],
            'date_premiere_echeance' => ['nullable', 'date', 'after_or_equal:date_contrat'],
            'taux_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observation' => ['nullable', 'string', 'max:5000'],
        ]);

        $donnees['remise'] ??= 0;
        $donnees['taux_acompte'] ??= 0;
        $donnees['taux_commission'] ??= 0;
        $donnees['nombre_echeances'] ??= 0;
        $donnees['periodicite_mois'] ??= 1;

        $this->service->definirConditions($vente, $donnees);

        return back()->with('success', 'Conditions enregistrées, échéancier recalculé.');
    }

    public function valider(Request $request, Vente $vente)
    {
        if ($attente = $this->soumettre('VENTE_VALIDATION', $vente)) {
            return $attente;
        }
        $this->service->valider($vente, $request->user());
        Audit::logSave(['action' => 'VALIDER', 'code_piece' => $vente->numero, 'menu' => 'CONTRATS DE VENTE', 'etat' => 'Succès', 'objet' => 'VENTES']);

        return back()->with('success', 'Contrat ' . $vente->numero . ' validé : échéancier généré et biens marqués vendus.');
    }

    public function desister(Request $request, Vente $vente)
    {
        $donnees = $request->validate([
            'motif' => ['required', Rule::in(Referentiel::options('motif_desistement')->keys())],
            'observation' => ['nullable', 'string', 'max:2000'],
            'taux_retenue' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($attente = $this->soumettre('VENTE_DESISTEMENT', $vente, $donnees, $donnees['observation'] ?? null, $donnees['motif'])) {
            return $attente;
        }
        $this->service->desister($vente, $donnees['motif'], $donnees['observation'] ?? null, $donnees['taux_retenue']);
        Audit::logSave(['action' => 'DESISTER', 'code_piece' => $vente->numero, 'menu' => 'CONTRATS DE VENTE', 'etat' => 'Succès', 'objet' => 'VENTES']);

        return back()->with('success', 'Désistement enregistré. Montant à rembourser : ' . montant($vente->montant_a_rembourser) . '.');
    }

    public function rembourser(Request $request, Vente $vente)
    {
        $donnees = $request->validate([
            'mode_paiement' => ['required', Rule::in(Referentiel::options('mode_paiement')->keys())],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $depense = $this->service->rembourser($vente, $donnees['mode_paiement'], $donnees['reference'] ?? null);
        Audit::logSave(['action' => 'REMBOURSER', 'code_piece' => $vente->numero, 'menu' => 'CONTRATS DE VENTE', 'etat' => 'Succès', 'objet' => 'VENTES']);

        return back()->with('success', 'Remboursement enregistré (dépense ' . $depense->numero . ').');
    }

    public function deposerDocument(Request $request, Vente $vente, VenteFrais $frais)
    {
        abort_unless($frais->vente_id === $vente->id, 404);
        $donnees = $request->validate([
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'statut_document' => ['required', Rule::in(array_keys(VenteFrais::STATUTS_DOCUMENT))],
        ]);

        if ($request->hasFile('document')) {
            $frais->document = $request->file('document')->store('agences/' . agence()->id . '/ventes', config('immobilier.stockage.prive'));
        }
        $frais->statut_document = $donnees['statut_document'];
        if ($donnees['statut_document'] === 'notifie') {
            $frais->document_notifie_le = now();
        }
        $frais->save();

        return back()->with('success', 'Document mis à jour.');
    }

    public function contrat(Vente $vente)
    {
        abort_if($vente->statut === StatutVente::Brouillon, 404);
        $vente->load(['acquereur.nationalite', 'lignes.bien.lotissement', 'lignes.bien.programme', 'frais', 'echeances']);

        return Pdf::telecharger('pdf.contrat_vente', ['vente' => $vente], 'contrat-' . $vente->numero, objet: $vente, type: 'CONTRAT_VENTE',
            etat: md5($vente->montant_net . '|' . $vente->montant_frais . '|' . $vente->statut->value . '|' . $vente->echeances->count()));
    }

    public function destroy(Vente $vente)
    {
        $this->service->supprimerBrouillon($vente);

        return redirect()->route('ventes.index')->with('success', 'Brouillon supprimé.');
    }
}
