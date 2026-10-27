<?php

namespace App\Http\Controllers\Location;

use App\Enums\NatureLoyer;
use App\Enums\StatutPaiement;
use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Loyer;
use App\Services\Location\BailService;
use App\Support\Pdf;
use App\Support\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LoyerController extends Controller
{
    public function index(Request $request)
    {
        $mois = $request->filled('mois') ? Carbon::parse($request->mois . '-01') : today()->startOfMonth();
        $filtre = $request->query('filtre', 'mois');

        $loyers = Loyer::with(['bail.locataire', 'bail.bien'])
            ->when($filtre === 'mois', fn ($q) => $q->whereBetween('date_echeance', [$mois->copy()->startOfMonth(), $mois->copy()->endOfMonth()]))
            ->when($filtre === 'impayes', fn ($q) => $q->enRetard())
            ->when($request->filled('nature'), fn ($q) => $q->where('nature', $request->nature))
            ->orderBy('date_echeance')->orderBy('id');

        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            $tous = $loyers->limit(20000)->get();

            return Export::telecharger($request->query('format'), $filtre === 'impayes' ? 'Loyers impayés' : 'Loyers ' . $mois->translatedFormat('F Y'),
                ['Échéance', 'Bail', 'Locataire', 'Bien', 'Libellé', 'Nature', 'Montant', 'Payé', 'Reste', 'Statut', 'Quittance'],
                $tous->map(fn ($l) => [$l->date_echeance->format('d/m/Y'), $l->bail->numero, $l->bail->locataire?->nom_complet, $l->bail->bien?->reference, $l->libelle,
                    $l->nature->libelle(), (float) $l->montant, (float) $l->montant_paye, (float) $l->resteAPayer(), $l->statut->libelle(), $l->quittance_numero])->all(),
                ['Totaux', '', '', '', '', '', (float) $tous->sum('montant'), (float) $tous->sum('montant_paye'), (float) $tous->sum(fn ($l) => (float) $l->resteAPayer()), '', '']);
        }
        $loyers = $loyers->paginate(50)->withQueryString();

        $base = Loyer::where('nature', NatureLoyer::Loyer->value)->where('statut', '!=', StatutPaiement::Annule->value)
            ->whereBetween('date_echeance', [$mois->copy()->startOfMonth(), $mois->copy()->endOfMonth()]);

        return view('location.loyers', [
            'loyers' => $loyers,
            'mois' => $mois,
            'filtre' => $filtre,
            'appele' => (clone $base)->sum('montant'),
            'encaisse' => (clone $base)->sum('montant_paye'),
            'impayes' => Loyer::enRetard()->selectRaw('coalesce(sum(montant - montant_paye), 0) as t')->value('t'),
        ]);
    }

    public function generer(Request $request, BailService $service)
    {
        $jusquAu = $request->filled('mois') ? Carbon::parse($request->mois . '-01')->endOfMonth() : today()->endOfMonth();
        $nombre = $service->genererPourTous($jusquAu);

        Audit::logSave(['action' => 'GENERER LOYERS', 'code_piece' => $jusquAu->format('Y-m'), 'menu' => 'LOYERS', 'etat' => 'Succès', 'objet' => 'GESTION LOCATIVE']);

        return back()->with('success', $nombre . ' avis d\'échéance généré(s) jusqu\'au ' . $jusquAu->format('d/m/Y') . '.');
    }

    public function penalites(BailService $service)
    {
        $nombre = $service->appliquerPenalites();

        return back()->with('success', $nombre . ' pénalité(s) de retard appliquée(s).');
    }

    public function quittance(Loyer $loyer)
    {
        abort_unless($loyer->estSolde() && $loyer->quittance_numero, 404, 'La quittance n\'est délivrée qu\'une fois le loyer intégralement payé.');
        $loyer->load(['bail.locataire', 'bail.bien.proprietaire', 'affectations.encaissement']);

        return Pdf::telecharger('pdf.quittance', ['loyer' => $loyer], 'quittance-' . $loyer->quittance_numero, objet: $loyer, type: 'QUITTANCE', etat: $loyer->quittance_numero);
    }
}
