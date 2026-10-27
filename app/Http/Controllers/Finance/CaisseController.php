<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use App\Models\Encaissement;
use App\Models\Referentiel;
use App\Support\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Journal de caisse : entrées (encaissements valides) et sorties (dépenses payées) d'une période,
 * soldes par mode de paiement.
 */
class CaisseController extends Controller
{
    public function index(Request $request)
    {
        $du = $request->filled('du') ? Carbon::parse($request->du) : today();
        $au = $request->filled('au') ? Carbon::parse($request->au) : $du->copy();

        $entrees = Encaissement::with('tiers')->valides()->whereBetween('date_encaissement', [$du->toDateString(), $au->toDateString()])
            ->orderBy('date_encaissement')->orderBy('id')->get();
        $sorties = Depense::with(['fournisseur', 'beneficiaire'])->where('statut', 'payee')
            ->whereBetween('date_depense', [$du->toDateString(), $au->toDateString()])->orderBy('date_depense')->get();

        $modes = Referentiel::options('mode_paiement');
        $parMode = collect($modes)->map(fn ($libelle, $code) => [
            'libelle' => $libelle,
            'entrees' => (float) $entrees->where('mode_paiement', $code)->sum('montant'),
            'sorties' => (float) $sorties->where('mode_paiement', $code)->sum('montant'),
        ])->filter(fn ($l) => $l['entrees'] || $l['sorties']);

        $mouvements = $entrees->map(fn ($e) => ['date' => $e->date_encaissement, 'piece' => $e->numero, 'libelle' => 'Encaissement — ' . $e->tiers?->nom_complet,
            'mode' => $modes[$e->mode_paiement] ?? $e->mode_paiement, 'entree' => (float) $e->montant, 'sortie' => 0, 'url' => route('encaissements.show', $e)])
            ->concat($sorties->map(fn ($d) => ['date' => $d->date_depense, 'piece' => $d->numero, 'libelle' => $d->libelle,
                'mode' => $modes[$d->mode_paiement] ?? $d->mode_paiement, 'entree' => 0, 'sortie' => (float) $d->montant, 'url' => route('depenses.edit', $d)]))
            ->sortBy(fn ($m) => $m['date']->timestamp)->values();

        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            return Export::telecharger($request->query('format'), 'Journal de caisse', ['Date', 'Pièce', 'Libellé', 'Mode', 'Entrée', 'Sortie'],
                $mouvements->map(fn ($m) => [$m['date']->format('d/m/Y'), $m['piece'], $m['libelle'], $m['mode'], (float) $m['entree'], (float) $m['sortie']])->all(),
                ['Totaux', '', '', '', (float) $mouvements->sum('entree'), (float) $mouvements->sum('sortie')], 'Du ' . $du->format('d/m/Y') . ' au ' . $au->format('d/m/Y'));
        }

        return view('finance.caisse', compact('du', 'au', 'mouvements', 'parMode'));
    }
}
