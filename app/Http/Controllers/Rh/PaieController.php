<?php

namespace App\Http\Controllers\Rh;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Referentiel;
use App\Models\Rh\Bulletin;
use App\Models\Rh\Employe;
use App\Models\Rh\PeriodePaie;
use App\Models\Rh\Rubrique;
use App\Models\Rh\VariablePaie;
use App\Services\Rh\PaieService;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaieController extends Controller
{
    public function __construct(protected PaieService $service)
    {
    }

    public function index()
    {
        return view('rh.paie', [
            'periodes' => PeriodePaie::withCount('bulletins')->orderByDesc('date_debut')->paginate(24),
            'masse' => PeriodePaie::where('date_debut', '>=', today()->startOfYear())->sum('total_cout'),
        ]);
    }

    public function ouvrir(Request $request)
    {
        $mois = $request->validate(['mois' => ['required', 'date_format:Y-m']])['mois'];
        $periode = $this->service->periode($mois . '-01');

        return redirect()->route('paie.show', $periode)->with('success', 'Période ' . $periode->libelle . ' ouverte.');
    }

    public function show(PeriodePaie $periode)
    {
        $periode->load('bulletins.employe');

        return view('rh.periode', [
            'periode' => $periode,
            'employes' => $this->service->contrats($periode)->pluck('employe'),
            'variables' => $periode->variables()->with('employe')->get(),
            'rubriquesSaisie' => Rubrique::where('mode_calcul', 'SAISIE')->where('actif', true)->orderBy('ordre')->pluck('libelle', 'code'),
            'modes' => Referentiel::options('mode_paiement'),
        ]);
    }

    public function variable(Request $request, PeriodePaie $periode)
    {
        abort_unless($periode->estModifiable(), 403, 'Période validée.');
        $donnees = $request->validate([
            'employe_id' => ['required', Rule::exists('rh_employes', 'id')->where('agence_id', agence()->id)],
            'rubrique_code' => ['required', Rule::in(Rubrique::where('mode_calcul', 'SAISIE')->pluck('code'))],
            'montant' => ['required', 'numeric'],
            'libelle' => ['nullable', 'string', 'max:255'],
        ]);
        $periode->variables()->create($donnees);

        return back()->with('success', 'Élément variable ajouté : recalculez la paie.');
    }

    public function supprimerVariable(PeriodePaie $periode, VariablePaie $variable)
    {
        abort_unless($periode->estModifiable() && $variable->periode_id === $periode->id, 403);
        $variable->delete();

        return back()->with('success', 'Élément variable supprimé.');
    }

    public function calculer(PeriodePaie $periode)
    {
        $n = $this->service->calculerTout($periode);
        Audit::logSave(['action' => 'CALCULER PAIE', 'code_piece' => $periode->code, 'menu' => 'PAIE', 'etat' => 'Succès', 'objet' => 'RH']);

        return back()->with('success', $n . ' bulletin(s) calculé(s).');
    }

    public function valider(PeriodePaie $periode)
    {
        if ($attente = $this->soumettre('PAIE_VALIDATION', $periode)) {
            return $attente;
        }
        $this->service->valider($periode);
        Audit::logSave(['action' => 'VALIDER PAIE', 'code_piece' => $periode->code, 'menu' => 'PAIE', 'etat' => 'Succès', 'objet' => 'RH']);

        return back()->with('success', 'Paie ' . $periode->libelle . ' validée : bulletins figés, avances remboursées, écriture comptable passée.');
    }

    public function payer(Request $request, PeriodePaie $periode)
    {
        $donnees = $request->validate([
            'bulletin_id' => ['nullable', Rule::exists('rh_bulletins', 'id')->where('periode_id', $periode->id)],
            'mode_paiement' => ['required', Rule::in(Referentiel::options('mode_paiement')->keys())],
            'montant' => ['nullable', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'date_paiement' => ['required', 'date'],
        ]);

        $bulletins = isset($donnees['bulletin_id'])
            ? $periode->bulletins()->whereKey($donnees['bulletin_id'])->get()
            : $periode->bulletins()->whereIn('statut', ['valide', 'partiel'])->get();

        foreach ($bulletins as $b) {
            $this->service->payer($b, $donnees + ['montant' => isset($donnees['bulletin_id']) ? ($donnees['montant'] ?? null) : null]);
        }

        return back()->with('success', $bulletins->count() . ' paiement(s) de salaire enregistré(s).');
    }

    public function bulletin(PeriodePaie $periode, Bulletin $bulletin)
    {
        abort_unless($bulletin->periode_id === $periode->id, 404);
        $bulletin->load(['employe', 'contrat', 'periode']);

        return Pdf::telecharger('pdf.bulletin', ['bulletin' => $bulletin], 'bulletin-' . $bulletin->employe->matricule . '-' . $periode->code,
            objet: $bulletin, type: 'BULLETIN', etat: md5($bulletin->net_a_payer . '|' . $periode->statut));
    }

    public function livre(PeriodePaie $periode)
    {
        $periode->load('bulletins.employe');

        return Pdf::telecharger('pdf.livre_paie', ['periode' => $periode], 'livre-de-paie-' . $periode->code, 'landscape',
            objet: $periode, type: 'LIVRE_PAIE', etat: md5($periode->statut . '|' . $periode->bulletins->sum('net_a_payer')));
    }
}
