<?php

namespace App\Http\Controllers\Vente;

use App\Enums\StatutReservation;
use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Reservation;
use App\Models\Tiers;
use App\Services\Vente\ReservationService;
use App\Services\Vente\VenteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function __construct(protected ReservationService $service)
    {
    }

    public function index(Request $request)
    {
        $reservations = Reservation::with(['tiers', 'bien', 'commercial', 'vente'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut), fn ($q) => $q->where('statut', StatutReservation::Active->value))
            ->latest('date_reservation')
            ->paginate(25)->withQueryString();

        return view('vente.reservations', compact('reservations'));
    }

    public function create(Request $request)
    {
        return view('vente.reservation_form', [
            'biens' => Bien::disponibles()->where('a_vendre', true)->orderBy('reference')->get(),
            'bienId' => $request->query('bien_id'),
            'tiers' => Tiers::orderBy('nom')->get(),
            'duree' => parametre('vente.duree_reservation_jours'),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'tiers_id' => ['required', Rule::exists('tiers', 'id')->where('agence_id', agence()->id)],
            'bien_id' => ['required', Rule::exists('biens', 'id')->where('agence_id', agence()->id)],
            'jours' => ['nullable', 'integer', 'min:1', 'max:365'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        $reservation = $this->service->reserver(
            Tiers::findOrFail($donnees['tiers_id']),
            Bien::findOrFail($donnees['bien_id']),
            $request->user(),
            $donnees['jours'] ?? null,
            $donnees['commentaire'] ?? null,
        );

        Audit::logSave(['action' => 'RESERVER', 'code_piece' => $reservation->numero, 'menu' => 'RESERVATIONS', 'etat' => 'Succès', 'objet' => 'VENTES']);

        return redirect()->route('reservations.index')->with('success', 'Bien ' . $reservation->bien->reference . ' réservé jusqu\'au ' . $reservation->date_expiration->format('d/m/Y') . '.');
    }

    public function liberer(Request $request, Reservation $reservation)
    {
        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:255']])['motif'] ?? 'Libération manuelle';
        $this->service->liberer($reservation, $motif);

        Audit::logSave(['action' => 'LIBERER', 'code_piece' => $reservation->numero, 'menu' => 'RESERVATIONS', 'etat' => 'Succès', 'objet' => 'VENTES']);

        return back()->with('success', 'Réservation libérée : le bien est de nouveau disponible.');
    }

    public function convertir(Request $request, Reservation $reservation, VenteService $ventes)
    {
        $vente = $ventes->creerDepuisReservation($reservation, $request->user());

        return redirect()->route('ventes.show', $vente)->with('success', 'Contrat de vente créé à partir de la réservation.');
    }
}
