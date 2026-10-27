<?php

namespace App\Services\Recouvrement;

use App\Models\AppelLigne;
use App\Models\Echeance;
use App\Models\Encaissement;
use App\Models\Loyer;
use App\Models\PromessePaiement;
use App\Models\Recouvrement;
use App\Models\RegleRelance;
use App\Models\Relance;
use App\Models\Sejour;
use App\Models\Tiers;
use App\Models\User;
use App\Services\Integration\NotificationService;
use App\Services\Parametres;
use App\Support\AgenceCourante;
use App\Support\Montant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recouvrement des impayés échus, toutes activités :
 *  analyser()             : crée / met à jour un dossier par débiteur et activité, solde les dossiers régularisés
 *  relancer()             : relance manuelle ou automatique selon les niveaux paramétrés (J+n)
 *  relancesAutomatiques() : tâche quotidienne
 *  promettre() / verifierPromesses() : promesses de paiement tenues ou non
 */
class RecouvrementService
{
    public function __construct(protected Parametres $parametres, protected AgenceCourante $agenceCourante)
    {
    }

    /**
     * Dettes échues et non soldées, regroupées par débiteur et activité.
     *
     * @return Collection<string, array{tiers_id: int, activite: string, montant: float, nombre: int, plus_ancienne: string, dettes: Collection}>
     */
    public function dettesEchues(?int $tiersId = null, ?string $activite = null): Collection
    {
        $lignes = collect();
        $aujourdhui = today();

        if (!$activite || $activite === 'vente') {
            Echeance::with('vente')->enRetard()->whereHas('vente', fn ($q) => $q->where('statut', 'en_cours')->when($tiersId, fn ($v) => $v->where('acquereur_id', $tiersId)))
                ->get()->each(fn ($e) => $lignes->push(['tiers_id' => $e->vente->acquereur_id, 'activite' => 'vente', 'dette' => $e, 'date' => $e->date_echeance]));
        }
        if (!$activite || $activite === 'location') {
            Loyer::with('bail')->enRetard()->whereHas('bail', fn ($q) => $q->when($tiersId, fn ($b) => $b->where('locataire_id', $tiersId)))
                ->get()->each(fn ($l) => $lignes->push(['tiers_id' => $l->bail->locataire_id, 'activite' => 'location', 'dette' => $l, 'date' => $l->date_echeance]));
        }
        if (!$activite || $activite === 'syndic') {
            AppelLigne::with('appel', 'lot')->enRetard()->whereNotNull('coproprietaire_id')->when($tiersId, fn ($q) => $q->where('coproprietaire_id', $tiersId))
                ->whereHas('appel', fn ($q) => $q->where('statut', 'emis'))
                ->get()->each(fn ($a) => $lignes->push(['tiers_id' => $a->coproprietaire_id, 'activite' => 'syndic', 'dette' => $a, 'date' => $a->appel->date_echeance]));
        }
        if (!$activite || $activite === 'meuble') {
            Sejour::impayes()->whereDate('date_depart', '<', $aujourdhui)->when($tiersId, fn ($q) => $q->where('tiers_id', $tiersId))
                ->get()->each(fn ($s) => $lignes->push(['tiers_id' => $s->tiers_id, 'activite' => 'meuble', 'dette' => $s, 'date' => $s->date_depart]));
        }

        return $lignes->groupBy(fn ($l) => $l['tiers_id'] . '|' . $l['activite'])->map(fn ($groupe) => [
            'tiers_id' => $groupe->first()['tiers_id'],
            'activite' => $groupe->first()['activite'],
            'montant' => round($groupe->sum(fn ($l) => (float) $l['dette']->resteAPayer()), 2),
            'nombre' => $groupe->count(),
            'plus_ancienne' => $groupe->min(fn ($l) => Carbon::parse($l['date'])->toDateString()),
            'dettes' => $groupe->sortBy(fn ($l) => Carbon::parse($l['date'])->timestamp)->pluck('dette')->values(),
        ]);
    }

    /**
     * Synchronise les dossiers de recouvrement avec les impayés réels.
     *
     * @return array{ouverts: int, soldes: int}
     */
    public function analyser(): array
    {
        $dettes = $this->dettesEchues();
        $seuil = (float) $this->parametres->get('recouvrement.seuil_minimum');
        $ouverts = 0;

        DB::transaction(function () use ($dettes, $seuil, &$ouverts) {
            foreach ($dettes as $groupe) {
                if ($groupe['montant'] < max(0.01, $seuil)) {
                    continue;
                }
                $dossier = Recouvrement::firstOrNew(['tiers_id' => $groupe['tiers_id'], 'activite' => $groupe['activite']]);
                $rouvert = $dossier->exists && !$dossier->estOuvert();
                $dossier->fill([
                    'montant_du' => $groupe['montant'],
                    'nombre_impayes' => $groupe['nombre'],
                    'plus_ancienne_echeance' => $groupe['plus_ancienne'],
                ]);
                if (!$dossier->exists || $rouvert) {
                    $dossier->fill(['statut' => 'amiable', 'niveau' => 0, 'solde_le' => null, 'gestionnaire_id' => $dossier->gestionnaire_id ?? $this->gestionnaireParDefaut($groupe)]);
                }
                $dossier->save();
                $ouverts++;
            }

            // dossiers ouverts dont l'impayé a disparu : soldés
            $cles = $dettes->filter(fn ($g) => $g['montant'] >= max(0.01, $seuil))->keys()->all();
            Recouvrement::whereNotIn('statut', ['solde', 'abandonne'])->get()
                ->reject(fn ($d) => in_array($d->tiers_id . '|' . $d->activite, $cles, true))
                ->each(fn ($d) => $d->update(['statut' => 'solde', 'montant_du' => 0, 'nombre_impayes' => 0, 'solde_le' => now()]));
        });

        return ['ouverts' => $ouverts, 'soldes' => Recouvrement::where('statut', 'solde')->whereDate('solde_le', today())->count()];
    }

    public function details(Recouvrement $dossier): Collection
    {
        return $this->dettesEchues($dossier->tiers_id, $dossier->activite)->first()['dettes'] ?? collect();
    }

    /**
     * Envoie (e-mail / SMS) ou prépare (courrier, appel) une relance du dossier.
     */
    public function relancer(Recouvrement $dossier, ?RegleRelance $regle = null, ?string $canal = null, ?string $message = null, ?User $auteur = null, bool $automatique = false): Relance
    {
        if (!$dossier->estOuvert()) {
            throw new DomainException('Ce dossier est clôturé.');
        }

        $regle ??= RegleRelance::where('actif', true)->where('niveau', '>', $dossier->niveau)->orderBy('niveau')->first()
            ?? RegleRelance::where('actif', true)->orderByDesc('niveau')->first();
        $canal ??= $regle?->canal ?? 'email';
        $tiers = $dossier->tiers;
        $objet = $this->remplir($regle?->objet ?? 'Relance de paiement', $dossier);
        $texte = $this->remplir($message ?: ($regle?->message ?? 'Bonjour {nom}, un montant de {montant} reste dû. {agence}'), $dossier);

        $statut = match ($canal) {
            'email' => $tiers->email ? 'envoyee' : 'echec',
            'sms', 'whatsapp' => $tiers->telephone ? 'envoyee' : 'echec',
            'courrier' => 'a_imprimer',
            default => 'realisee',
        };

        if ($statut === 'envoyee') {
            try {
                $canal === 'email'
                    ? NotificationService::enFile('EMAIL', $tiers->email, nl2br(e($texte)), $objet, $tiers->nom_complet, 'RELANCE', $tiers)
                    : NotificationService::enFile(strtoupper($canal), ($tiers->indicatif ?: '') . preg_replace('/\D/', '', (string) $tiers->telephone), $texte, null, null, 'RELANCE', $tiers);
            } catch (\Throwable $e) {
                report($e);
                $statut = 'echec';
            }
        }

        return DB::transaction(function () use ($dossier, $regle, $canal, $objet, $texte, $statut, $auteur, $automatique) {
            $relance = $dossier->relances()->create([
                'agence_id' => $dossier->agence_id,
                'niveau' => $regle?->niveau ?? $dossier->niveau,
                'canal' => $canal,
                'montant_du' => $dossier->montant_du,
                'objet' => $objet,
                'message' => $texte,
                'statut' => $statut,
                'automatique' => $automatique,
                'user_id' => $auteur?->id ?? auth()->id(),
            ]);

            $dossier->update([
                'niveau' => max($dossier->niveau, $regle?->niveau ?? 0),
                'prochaine_action' => today()->addDays((int) $this->parametres->get('recouvrement.delai_entre_relances_jours')),
                'statut' => ($regle?->niveau ?? 0) >= 4 && $dossier->statut === 'amiable' ? 'contentieux' : $dossier->statut,
            ]);

            return $relance;
        });
    }

    /**
     * Relances automatiques : niveau le plus élevé atteint par le retard, une relance par dossier au plus
     * tous les N jours ; les dossiers sous promesse, en protocole ou en contentieux ne sont pas relancés automatiquement.
     */
    public function relancesAutomatiques(): int
    {
        if (!$this->parametres->get('recouvrement.relances_automatiques')) {
            return 0;
        }

        $regles = RegleRelance::where('actif', true)->where('automatique', true)->orderBy('niveau')->get();
        $delai = (int) $this->parametres->get('recouvrement.delai_entre_relances_jours');
        $envoyees = 0;

        foreach (Recouvrement::with('tiers')->where('statut', 'amiable')->get() as $dossier) {
            $retard = $dossier->joursRetard();
            $regle = $regles->filter(fn ($r) => $r->delai_jours <= $retard && $r->niveau > $dossier->niveau && $r->concerne($dossier->activite))->last();
            $derniere = $dossier->relances()->latest()->value('created_at');

            if ($regle && (!$derniere || Carbon::parse($derniere)->lte(now()->subDays($delai)))) {
                $this->relancer($dossier, $regle, null, null, null, true);
                $envoyees++;
            }
        }

        return $envoyees;
    }

    public function promettre(Recouvrement $dossier, Carbon $date, $montant, ?string $commentaire = null): PromessePaiement
    {
        if ($date->isPast() && !$date->isToday()) {
            throw new DomainException('La date de la promesse doit être aujourd\'hui ou plus tard.');
        }

        $promesse = $dossier->promesses()->create([
            'agence_id' => $dossier->agence_id, 'date_promesse' => $date, 'montant' => Montant::normaliser($montant),
            'commentaire' => $commentaire, 'user_id' => auth()->id(),
        ]);
        $dossier->update(['statut' => 'promesse', 'prochaine_action' => $date]);

        return $promesse;
    }

    /**
     * Promesses arrivées à échéance : tenues si le débiteur a payé au moins le montant promis depuis la promesse.
     */
    public function verifierPromesses(): int
    {
        $traitees = 0;
        foreach (PromessePaiement::with('recouvrement')->where('statut', 'en_attente')->whereDate('date_promesse', '<', today())->get() as $promesse) {
            $paye = (float) Encaissement::valides()->where('tiers_id', $promesse->recouvrement->tiers_id)
                ->where('created_at', '>=', $promesse->created_at)->sum('montant');
            $tenue = $paye + 0.001 >= (float) $promesse->montant;

            $promesse->update(['statut' => $tenue ? 'tenue' : 'non_tenue']);
            if ($promesse->recouvrement->statut === 'promesse') {
                $promesse->recouvrement->update(['statut' => 'amiable', 'prochaine_action' => today()]);
            }
            $traitees++;
        }

        return $traitees;
    }

    public function changerStatut(Recouvrement $dossier, string $statut, ?string $commentaire = null): void
    {
        if (!array_key_exists($statut, Recouvrement::STATUTS) || $statut === 'solde') {
            throw new DomainException('Statut invalide.');
        }

        $dossier->update(['statut' => $statut, 'commentaire' => trim(($dossier->commentaire ? $dossier->commentaire . "\n" : '') . ($commentaire ? now()->format('d/m/Y') . ' : ' . $commentaire : '')) ?: null]);
    }

    /**
     * Remplace les variables du modèle : {nom} {montant} {retard} {agence} {details}.
     */
    public function remplir(string $modele, Recouvrement $dossier): string
    {
        $details = $this->details($dossier)->map(fn ($d) => '- ' . $d->libelleAffectation() . ' : ' . montant($d->resteAPayer()))->implode("\n");
        $agence = $this->agenceCourante->agence();

        return strtr($modele, [
            '{nom}' => $dossier->tiers->nom_complet,
            '{montant}' => montant($dossier->montant_du),
            '{retard}' => (string) $dossier->joursRetard(),
            '{agence}' => trim(($agence?->raison_sociale ?? '') . ($agence?->telephone ? ' — ' . $agence->telephone : '')),
            '{details}' => $details,
        ]);
    }

    protected function gestionnaireParDefaut(array $groupe): ?int
    {
        return Tiers::find($groupe['tiers_id'])?->commercial_id;
    }
}
