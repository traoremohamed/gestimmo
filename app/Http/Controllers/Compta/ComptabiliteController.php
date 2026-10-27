<?php

namespace App\Http\Controllers\Compta;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Compta\Compte;
use App\Models\Compta\Ecriture;
use App\Models\Compta\Exercice;
use App\Services\Compta\ComptaService;
use App\Support\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * États comptables : balance, grand livre, bilan et compte de résultat, exercices, export des écritures.
 */
class ComptabiliteController extends Controller
{
    public function __construct(protected ComptaService $compta)
    {
    }

    protected function periode(Request $request): array
    {
        $exercice = Exercice::whereDate('date_debut', '<=', today())->whereDate('date_fin', '>=', today())->first();

        return [
            $request->filled('du') ? Carbon::parse($request->du) : ($exercice?->date_debut ?? today()->startOfYear()),
            $request->filled('au') ? Carbon::parse($request->au) : today(),
        ];
    }

    public function index(Request $request)
    {
        [$du, $au] = $this->periode($request);
        $vue = $request->query('vue', 'balance');
        $periode = 'Du ' . $du->format('d/m/Y') . ' au ' . $au->format('d/m/Y');

        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            if ($vue === 'etats') {
                $e = $this->compta->etatsFinanciers($du, $au);
                $lignes = collect()
                    ->concat($e['actif']->map(fn ($l) => ['Bilan — Actif', $l->numero, $l->libelle, (float) $l->solde]))
                    ->concat($e['passif']->map(fn ($l) => ['Bilan — Passif', $l->numero, $l->libelle, -(float) $l->solde]))
                    ->push(['Bilan — Passif', '', 'Résultat cumulé', (float) $e['resultat_cumule']])
                    ->concat($e['charges']->map(fn ($l) => ['Résultat — Charges', $l->numero, $l->libelle, (float) $l->solde]))
                    ->concat($e['produits']->map(fn ($l) => ['Résultat — Produits', $l->numero, $l->libelle, -(float) $l->solde]))
                    ->push(['Résultat de la période', '', $e['resultat'] >= 0 ? 'Bénéfice' : 'Perte', (float) $e['resultat']]);

                return Export::telecharger($request->query('format'), 'Bilan et compte de résultat', ['Rubrique', 'Compte', 'Libellé', 'Montant'], $lignes->all(), null, $periode, 'portrait');
            }
            $balance = $this->compta->balance($du, $au, $request->integer('classe') ?: null);

            return Export::telecharger($request->query('format'), 'Balance générale', ['Compte', 'Libellé', 'Débit', 'Crédit', 'Solde débiteur', 'Solde créditeur'],
                $balance->map(fn ($l) => [$l->numero, $l->libelle, (float) $l->debit, (float) $l->credit, max(0, (float) $l->solde), max(0, -(float) $l->solde)])->all(),
                ['Totaux', '', (float) $balance->sum('debit'), (float) $balance->sum('credit'), (float) $balance->sum(fn ($l) => max(0, (float) $l->solde)),
                    (float) $balance->sum(fn ($l) => max(0, -(float) $l->solde))], $periode);
        }

        return view('compta.etats', [
            'vue' => $vue, 'du' => $du, 'au' => $au,
            'balance' => $vue === 'balance' ? $this->compta->balance($du, $au, $request->integer('classe') ?: null) : collect(),
            'etats' => $vue === 'etats' ? $this->compta->etatsFinanciers($du, $au) : null,
            'exercices' => Exercice::orderByDesc('date_debut')->get(),
            'comptes' => Compte::orderBy('numero')->get(),
        ]);
    }

    public function grandLivre(Request $request, Compte $compte)
    {
        [$du, $au] = $this->periode($request);
        $donnees = $this->compta->grandLivre($compte, $du, $au);

        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            $solde = $donnees['report'];
            $lignes = [['', '', '', 'Report à nouveau', null, null, $solde]];
            foreach ($donnees['lignes'] as $l) {
                $solde += (float) $l->debit - (float) $l->credit;
                $lignes[] = [Carbon::parse($l->date_ecriture)->format('d/m/Y'), $l->numero, $l->piece, $l->libelle, (float) $l->debit, (float) $l->credit, round($solde, 2)];
            }

            return Export::telecharger($request->query('format'), 'Grand livre ' . $compte->numero . ' — ' . $compte->libelle, ['Date', 'Écriture', 'Pièce', 'Libellé', 'Débit', 'Crédit', 'Solde'],
                $lignes, ['Totaux', '', '', '', (float) $donnees['lignes']->sum('debit'), (float) $donnees['lignes']->sum('credit'), round($solde, 2)],
                'Du ' . $du->format('d/m/Y') . ' au ' . $au->format('d/m/Y'));
        }

        return view('compta.grand_livre', ['compte' => $compte, 'du' => $du, 'au' => $au] + $donnees);
    }

    public function cloturer(Exercice $exercice)
    {
        $resultat = $this->compta->cloturer($exercice);
        Audit::logSave(['action' => 'CLOTURE EXERCICE', 'code_piece' => $exercice->code, 'menu' => 'COMPTABILITE', 'etat' => 'Succès', 'objet' => 'COMPTABILITE']);

        return back()->with('success', 'Exercice ' . $exercice->code . ' clôturé — résultat : ' . montant($resultat['resultat']) . '.');
    }

    /**
     * Export des écritures (format proche du FEC) pour le cabinet comptable.
     */
    public function export(Request $request)
    {
        [$du, $au] = $this->periode($request);

        // Excel / PDF : toutes les lignes comptables de la période ; sans format : fichier proche du FEC pour le cabinet
        if (in_array($request->query('format'), ['xlsx', 'pdf'], true)) {
            $lignes = [];
            Ecriture::with(['journal', 'lignes.compte', 'lignes.tiers'])->whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])
                ->orderBy('date_ecriture')->orderBy('id')->chunk(500, function ($ecritures) use (&$lignes) {
                    foreach ($ecritures as $e) {
                        foreach ($e->lignes as $l) {
                            $lignes[] = [$e->date_ecriture->format('d/m/Y'), $e->journal->code, $e->numero, $e->piece, $l->compte->numero, $l->compte->libelle,
                                $l->tiers?->nom_complet, $l->libelle, (float) $l->debit, (float) $l->credit];
                        }
                    }
                });

            return Export::telecharger($request->query('format'), 'Journal des écritures comptables',
                ['Date', 'Journal', 'Écriture', 'Pièce', 'Compte', 'Intitulé du compte', 'Tiers', 'Libellé', 'Débit', 'Crédit'], $lignes,
                ['Totaux', '', '', '', '', '', '', count($lignes) . ' ligne(s)', round(array_sum(array_column($lignes, 8)), 2), round(array_sum(array_column($lignes, 9)), 2)],
                'Du ' . $du->format('d/m/Y') . ' au ' . $au->format('d/m/Y'));
        }

        return response()->streamDownload(function () use ($du, $au) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['JournalCode', 'EcritureNum', 'EcritureDate', 'CompteNum', 'CompteLib', 'Tiers', 'PieceRef', 'EcritureLib', 'Debit', 'Credit', 'Lettrage'], ';');
            Ecriture::with(['journal', 'lignes.compte', 'lignes.tiers'])->whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])
                ->orderBy('date_ecriture')->orderBy('id')->chunk(500, function ($ecritures) use ($sortie) {
                    foreach ($ecritures as $e) {
                        foreach ($e->lignes as $l) {
                            fputcsv($sortie, [$e->journal->code, $e->numero, $e->date_ecriture->format('Ymd'), $l->compte->numero, $l->compte->libelle, $l->tiers?->nom_complet,
                                $e->piece, $l->libelle, number_format((float) $l->debit, 2, ',', ''), number_format((float) $l->credit, 2, ',', ''), $l->lettrage], ';');
                        }
                    }
                });
            fclose($sortie);
        }, 'ecritures-' . $du->format('Ymd') . '-' . $au->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
