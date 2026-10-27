<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\Referentiel;
use App\Models\Rh\Absence;
use App\Models\Rh\Employe;
use App\Services\Rh\RhService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Congés et absences : un employé demande pour lui-même ; un valideur (absences-valider) décide pour tous.
 */
class AbsenceController extends Controller
{
    public function __construct(protected RhService $service)
    {
    }

    public function index(Request $request)
    {
        $absences = Absence::with(['employe', 'decideur'])
            ->when(!$this->valideur(), fn ($q) => $q->where('employe_id', $this->monDossier()?->id ?? 0))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('employe_id'), fn ($q) => $q->where('employe_id', $request->employe_id))
            ->latest('date_debut')->paginate(30)->withQueryString();

        $enCours = Absence::with('employe')->where('statut', 'validee')->whereDate('date_debut', '<=', today())->whereDate('date_fin', '>=', today())->get();

        return view('rh.absences', [
            'absences' => $absences,
            'enCours' => $this->valideur() ? $enCours : collect(),
            'employes' => $this->employesAutorises()->pluck('nom_complet', 'id'),
            'types' => Referentiel::options('type_absence'),
            'monDossier' => $this->monDossier(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'employe_id' => ['required', Rule::in($this->employesAutorises()->pluck('id'))],
            'type' => ['required', Rule::in(Referentiel::options('type_absence')->keys())],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'motif' => ['nullable', 'string', 'max:1000'],
        ], ['employe_id.in' => 'Vous ne pouvez demander une absence que pour vous-même.']);

        $absence = $this->service->demanderAbsence(Employe::findOrFail($donnees['employe_id']), $donnees['type'],
            Carbon::parse($donnees['date_debut']), Carbon::parse($donnees['date_fin']), $donnees['motif'] ?? null);

        // circuit de validation éventuel : la décision passe alors par ses étapes
        if ($attente = $this->soumettre('ABSENCE_APPROBATION', $absence, filtre: $absence->type, montant: (float) $absence->nombre_jours)) {
            return $attente;
        }

        return back()->with('success', 'Demande enregistrée : ' . (float) $absence->nombre_jours . ' jour(s) ouvrable(s), en attente de validation.');
    }

    public function decider(Request $request, Absence $absence)
    {
        $donnees = $request->validate(['decision' => ['required', Rule::in(['valider', 'refuser'])], 'commentaire' => ['nullable', 'string', 'max:255']]);
        if ($demande = \App\Services\Validation\ValidationService::enAttente($absence, 'ABSENCE_APPROBATION')) {
            return redirect()->route('validations.show', $demande)->with('info', 'Cette absence suit le circuit de validation ' . $demande->numero . ' : décidez depuis la demande.');
        }
        $this->service->decider($absence, $donnees['decision'] === 'valider', $donnees['commentaire'] ?? null);

        return back()->with('success', $donnees['decision'] === 'valider' ? 'Absence validée.' : 'Demande refusée.');
    }

    public function annuler(Absence $absence)
    {
        abort_unless($this->valideur() || ($absence->employe_id === $this->monDossier()?->id && $absence->statut === 'demandee'), 403);
        $this->service->annulerAbsence($absence);

        return back()->with('success', 'Absence annulée.');
    }

    protected function valideur(): bool
    {
        return auth()->user()->can('absences-valider');
    }

    protected function monDossier(): ?Employe
    {
        return Employe::where('user_id', auth()->id())->first();
    }

    protected function employesAutorises()
    {
        return $this->valideur() ? Employe::where('statut', 'actif')->orderBy('nom')->get() : collect([$this->monDossier()])->filter();
    }
}
