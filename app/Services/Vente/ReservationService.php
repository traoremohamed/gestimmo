<?php

namespace App\Services\Vente;

use App\Enums\RoleTiers;
use App\Enums\StatutBien;
use App\Enums\StatutReservation;
use App\Models\Bien;
use App\Models\Reservation;
use App\Models\Tiers;
use App\Models\User;
use App\Services\Numerotation;
use App\Services\Parametres;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Réservation d'un bien (intention d'achat) : le bien est bloqué jusqu'à expiration.
 */
class ReservationService
{
    public function __construct(protected Numerotation $numerotation, protected Parametres $parametres)
    {
    }

    public function reserver(Tiers $tiers, Bien $bien, ?User $commercial = null, ?int $jours = null, ?string $commentaire = null): Reservation
    {
        return DB::transaction(function () use ($tiers, $bien, $commercial, $jours, $commentaire) {
            $bien = Bien::whereKey($bien->id)->lockForUpdate()->firstOrFail();

            if (!$bien->estDisponible()) {
                throw new DomainException("Le bien {$bien->reference} n'est pas disponible ({$bien->statut->libelle()}).");
            }
            if (!$bien->a_vendre) {
                throw new DomainException("Le bien {$bien->reference} n'est pas proposé à la vente.");
            }

            $jours ??= $this->parametres->get('vente.duree_reservation_jours');

            $reservation = Reservation::create([
                'numero' => $this->numerotation->suivant('reservation'),
                'tiers_id' => $tiers->id,
                'bien_id' => $bien->id,
                'user_id' => $commercial?->id,
                'date_reservation' => today(),
                'date_expiration' => today()->addDays(max(1, $jours)),
                'statut' => StatutReservation::Active,
                'commentaire' => $commentaire,
            ]);

            $bien->update(['statut' => StatutBien::Reserve]);
            $tiers->ajouterRole(RoleTiers::Prospect);

            return $reservation;
        });
    }

    public function liberer(Reservation $reservation, ?string $motif = null, StatutReservation $statut = StatutReservation::Liberee): void
    {
        if (!$reservation->estActive()) {
            throw new DomainException('Seule une réservation active peut être libérée.');
        }

        DB::transaction(function () use ($reservation, $motif, $statut) {
            $reservation->update(['statut' => $statut, 'motif_liberation' => $motif]);

            $bien = Bien::whereKey($reservation->bien_id)->lockForUpdate()->first();
            if ($bien && $bien->statut === StatutBien::Reserve) {
                $bien->update(['statut' => StatutBien::Disponible]);
            }
        });
    }

    /**
     * Libère les réservations arrivées à expiration (tâche planifiée quotidienne).
     */
    public function expirer(): int
    {
        $expirees = Reservation::where('statut', StatutReservation::Active->value)->whereDate('date_expiration', '<', today())->get();

        foreach ($expirees as $reservation) {
            $this->liberer($reservation, 'Expiration automatique', StatutReservation::Expiree);
        }

        return $expirees->count();
    }

    /**
     * Le bien est-il réservé (réservation active) par ce tiers ?
     */
    public function estReservePar(Bien $bien, Tiers $tiers): bool
    {
        return Reservation::where(['bien_id' => $bien->id, 'tiers_id' => $tiers->id, 'statut' => StatutReservation::Active->value])->exists();
    }
}
