<?php

namespace App\Http\Controllers\Finance;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Encaissement;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Services\Finance\EncaissementService;
use App\Support\Montant;
use App\Support\Pdf;
use DomainException;
use App\Support\Export;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Caisse : encaissement des dettes d'un tiers (toutes activités) et reçus.
 * Seules les dettes ouvertes du tiers sélectionné peuvent être réglées ; le montant
 * de chaque ligne est plafonné à son reste à payer.
 */
class EncaissementController extends Controller
{
    public function __construct(protected EncaissementService $service)
    {
    }

    public function index(Request $request)
    {
        $encaissements = Encaissement::with(['tiers', 'caissier'])
            ->when($request->filled('du'), fn ($q) => $q->whereDate('date_encaissement', '>=', $request->du))
            ->when($request->filled('au'), fn ($q) => $q->whereDate('date_encaissement', '<=', $request->au))
            ->when($request->filled('activite'), fn ($q) => $q->where('activite', $request->activite))
            ->when($request->filled('mode_paiement'), fn ($q) => $q->where('mode_paiement', $request->mode_paiement))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('numero', 'ilike', '%' . $request->q . '%')
                ->orWhereHas('tiers', fn ($t) => $t->recherche($request->q))))
            ->latest('date_encaissement')->latest('id');

        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            $modes = Referentiel::options('mode_paiement');
            $lignes = $encaissements->limit(20000)->get();

            return Export::telecharger($request->query('format'), 'Encaissements', ['Date', 'Reçu', 'Payeur', 'Activité', 'Mode', 'Référence', 'Montant', 'Statut', 'Caissier'],
                $lignes->map(fn ($e) => [$e->date_encaissement->format('d/m/Y'), $e->numero, $e->tiers?->nom_complet, $e->activite, $modes[$e->mode_paiement] ?? $e->mode_paiement,
                    $e->reference, (float) $e->montant, $e->statut === 'valide' ? 'Valide' : 'Annulé', $e->caissier?->name])->all(),
                ['Total valide', '', '', '', '', '', (float) $lignes->where('statut', 'valide')->sum('montant'), '', '']);
        }
        $encaissements = $encaissements->paginate(30)->withQueryString();

        return view('finance.encaissements', ['encaissements' => $encaissements, 'modes' => Referentiel::options('mode_paiement')]);
    }

    public function create(Request $request)
    {
        $tiers = $request->filled('tiers_id') ? Tiers::find($request->tiers_id) : null;

        return view('finance.encaissement_form', [
            'tiers' => $tiers,
            'listeTiers' => Tiers::orderBy('nom')->get()->pluck('nom_complet', 'id'),
            'dettes' => $tiers ? $this->service->dettesOuvertes($tiers, $request->query('activite')) : collect(),
            'activite' => $request->query('activite'),
            'modes' => Referentiel::options('mode_paiement'),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'tiers_id' => ['required', Rule::exists('tiers', 'id')->where('agence_id', agence()->id)],
            'date_encaissement' => ['required', 'date', 'before_or_equal:today'],
            'mode_paiement' => ['required', Rule::in(Referentiel::options('mode_paiement')->keys())],
            'reference' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => in_array($request->mode_paiement, ['CHEQUE', 'VIREMENT'], true))],
            'banque' => ['nullable', 'string', 'max:255'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'montant_global' => ['nullable', 'numeric', 'min:0'],
            'lignes' => ['nullable', 'array'],
            'lignes.*' => ['nullable', 'numeric', 'min:0'],
        ], ['reference.required' => 'La référence est obligatoire pour un chèque ou un virement.']);

        $tiers = Tiers::findOrFail($donnees['tiers_id']);
        $dettes = $this->service->dettesOuvertes($tiers)->keyBy(fn ($d) => $d->getMorphClass() . ':' . $d->getKey());

        $affectations = [];
        foreach (array_filter($donnees['lignes'] ?? [], fn ($m) => (float) $m > 0) as $cle => $montant) {
            $dette = $dettes->get($cle) ?? throw new DomainException('Une ligne sélectionnée n\'appartient pas à ce tiers ou est déjà soldée.');
            $affectations[] = [$dette, Montant::normaliser($montant)];
        }

        if (empty($affectations) && !empty($donnees['montant_global'])) {
            $affectations = $this->service->repartir($dettes->values(), $donnees['montant_global']);
        }

        $encaissement = $this->service->encaisser($donnees + ['tiers_id' => $tiers->id], $affectations, $request->user());

        Audit::logSave(['action' => 'ENCAISSER', 'code_piece' => $encaissement->numero, 'menu' => 'ENCAISSEMENTS', 'etat' => 'Succès', 'objet' => 'CAISSE']);

        return redirect()->route('encaissements.show', $encaissement)->with('success', 'Encaissement ' . $encaissement->numero . ' de ' . montant($encaissement->montant) . ' enregistré.');
    }

    public function show(Encaissement $encaissement)
    {
        $encaissement->load(['tiers', 'caissier', 'affectations.affectable']);

        return view('finance.encaissement', compact('encaissement'));
    }

    public function recu(Encaissement $encaissement)
    {
        $encaissement->load(['tiers', 'caissier', 'affectations.affectable']);

        return Pdf::telecharger('pdf.recu', ['encaissement' => $encaissement], 'recu-' . $encaissement->numero, objet: $encaissement, type: 'RECU', etat: $encaissement->statut);
    }

    public function annuler(Request $request, Encaissement $encaissement)
    {
        $motif = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:1000']])['motif'];
        if ($attente = $this->soumettre('ENCAISSEMENT_ANNULATION', $encaissement, ['motif' => $motif], $motif)) {
            return $attente;
        }
        $this->service->annuler($encaissement, $motif, $request->user());

        Audit::logSave(['action' => 'ANNULER', 'code_piece' => $encaissement->numero, 'menu' => 'ENCAISSEMENTS', 'etat' => 'Succès', 'objet' => 'CAISSE']);

        return back()->with('success', 'Encaissement annulé : les dettes correspondantes ont été rouvertes.');
    }
}
