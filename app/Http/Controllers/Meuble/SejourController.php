<?php

namespace App\Http\Controllers\Meuble;

use App\Enums\StatutSejour;
use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Sejour;
use App\Models\Tiers;
use App\Services\Meuble\SejourService;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SejourController extends Controller
{
    public function __construct(protected SejourService $service)
    {
    }

    public function index(Request $request)
    {
        $debut = $request->filled('du') ? Carbon::parse($request->du) : today()->startOfWeek();
        $jours = collect(range(0, 13))->map(fn ($i) => $debut->copy()->addDays($i));
        $fin = $jours->last()->copy()->addDay();

        $biens = Bien::where('meuble', true)->orderBy('reference')
            ->with(['sejours' => fn ($q) => $q->with('client')->whereNotIn('statut', ['annule'])->where('date_arrivee', '<', $fin)->where('date_depart', '>', $debut)])
            ->get();

        $sejours = Sejour::with(['bien', 'client'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut), fn ($q) => $q->whereIn('statut', ['reserve', 'confirme', 'en_cours']))
            ->orderBy('date_arrivee')->paginate(25)->withQueryString();

        return view('meuble.sejours', compact('biens', 'jours', 'debut', 'sejours'));
    }

    public function create(Request $request)
    {
        return view('meuble.sejour_form', [
            'biens' => Bien::where('meuble', true)->orderBy('reference')->get(),
            'tiers' => Tiers::orderBy('nom')->get()->pluck('nom_complet', 'id'),
            'bienId' => $request->query('bien_id'),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'bien_id' => ['required', Rule::exists('biens', 'id')->where('agence_id', agence()->id)],
            'tiers_id' => ['required', Rule::exists('tiers', 'id')->where('agence_id', agence()->id)],
            'date_arrivee' => ['required', 'date'],
            'date_depart' => ['required', 'date', 'after:date_arrivee'],
            'nb_personnes' => ['required', 'integer', 'min:1', 'max:50'],
            'prix_nuitee' => ['nullable', 'numeric', 'min:0'],
            'remise' => ['nullable', 'numeric', 'min:0'],
            'frais_supplementaires' => ['nullable', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', 'max:30'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ]);

        $sejour = $this->service->reserver(Bien::findOrFail($donnees['bien_id']), Tiers::findOrFail($donnees['tiers_id']), $donnees);

        return redirect()->route('sejours.show', $sejour)->with('success', 'Séjour ' . $sejour->numero . ' réservé : ' . $sejour->nb_nuits . ' nuit(s), ' . montant($sejour->montant) . '.');
    }

    public function show(Sejour $sejour)
    {
        $sejour->load(['bien', 'client', 'affectations.encaissement']);

        return view('meuble.sejour', compact('sejour'));
    }

    public function statut(Request $request, Sejour $sejour)
    {
        $statut = StatutSejour::from($request->validate(['statut' => ['required', Rule::in(StatutSejour::valeurs())]])['statut']);
        $this->service->changerStatut($sejour, $statut);

        return back()->with('success', 'Séjour : ' . $statut->libelle() . '.');
    }

    public function facture(Sejour $sejour)
    {
        $sejour->load(['bien', 'client', 'affectations.encaissement']);

        return Pdf::telecharger('pdf.facture_sejour', ['sejour' => $sejour], 'facture-' . $sejour->numero, objet: $sejour, type: 'FACTURE_SEJOUR',
            etat: md5($sejour->montant . '|' . $sejour->montant_paye . '|' . $sejour->statut->value));
    }
}
