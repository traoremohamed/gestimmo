<?php

namespace App\Services\Meuble;

use App\Enums\RoleTiers;
use App\Enums\StatutSejour;
use App\Models\Bien;
use App\Models\Sejour;
use App\Models\Tiers;
use App\Services\Numerotation;
use App\Support\Montant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Séjours en résidence meublée : disponibilité, tarification à la nuitée, cycle arrivée / départ.
 */
class SejourService
{
    protected const TRANSITIONS = [
        'reserve' => ['confirme', 'en_cours', 'annule'],
        'confirme' => ['en_cours', 'annule'],
        'en_cours' => ['termine'],
        'termine' => [],
        'annule' => [],
    ];

    public function __construct(protected Numerotation $numerotation)
    {
    }

    public function reserver(Bien $bien, Tiers $client, array $donnees): Sejour
    {
        if (!$bien->meuble) {
            throw new DomainException("Le bien {$bien->reference} n'est pas proposé en meublé.");
        }

        $arrivee = Carbon::parse($donnees['date_arrivee'])->startOfDay();
        $depart = Carbon::parse($donnees['date_depart'])->startOfDay();
        $nuits = (int) $arrivee->diffInDays($depart);

        if ($nuits < 1) {
            throw new DomainException('La date de départ doit être postérieure à la date d\'arrivée.');
        }

        return DB::transaction(function () use ($bien, $client, $donnees, $arrivee, $depart, $nuits) {
            Bien::whereKey($bien->id)->lockForUpdate()->first();

            if (!$bien->estLibrePourSejour($arrivee, $depart)) {
                throw new DomainException("Le bien {$bien->reference} est déjà occupé sur cette période.");
            }

            $prix = Montant::normaliser($donnees['prix_nuitee'] ?? $bien->prix_nuitee);
            $remise = Montant::normaliser($donnees['remise'] ?? 0);
            $frais = Montant::normaliser($donnees['frais_supplementaires'] ?? 0);

            $sejour = Sejour::create([
                'numero' => $this->numerotation->suivant('sejour'),
                'bien_id' => $bien->id,
                'tiers_id' => $client->id,
                'date_arrivee' => $arrivee,
                'date_depart' => $depart,
                'nb_nuits' => $nuits,
                'nb_personnes' => $donnees['nb_personnes'] ?? 1,
                'prix_nuitee' => $prix,
                'remise' => $remise,
                'frais_supplementaires' => $frais,
                'montant' => Montant::sub(Montant::add(Montant::mul($prix, $nuits), $frais), $remise),
                'statut' => StatutSejour::Reserve,
                'source' => $donnees['source'] ?? null,
                'observation' => $donnees['observation'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $client->ajouterRole(RoleTiers::Client);

            return $sejour;
        });
    }

    public function changerStatut(Sejour $sejour, StatutSejour $statut): void
    {
        if (!in_array($statut->value, self::TRANSITIONS[$sejour->statut->value], true)) {
            throw new DomainException("Impossible de passer le séjour de « {$sejour->statut->libelle()} » à « {$statut->libelle()} ».");
        }
        if ($statut === StatutSejour::Annule && Montant::estPositif($sejour->montant_paye)) {
            throw new DomainException('Ce séjour a reçu des paiements : annulez d\'abord les encaissements.');
        }

        $sejour->update(['statut' => $statut]);
    }

    /**
     * Taux d'occupation d'un bien sur une période (nuits occupées / nuits de la période).
     */
    public function tauxOccupation(Bien $bien, Carbon $debut, Carbon $fin): float
    {
        $total = max(1, (int) $debut->diffInDays($fin));
        $occupees = 0;

        foreach ($bien->sejours()->whereNotIn('statut', ['annule'])->where('date_arrivee', '<', $fin)->where('date_depart', '>', $debut)->get() as $s) {
            $occupees += (int) $s->date_arrivee->max($debut)->diffInDays($s->date_depart->min($fin));
        }

        return round($occupees / $total * 100, 1);
    }
}
