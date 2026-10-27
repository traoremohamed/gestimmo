<?php

namespace App\Services\Marketing;

use App\Models\Campagne;
use App\Models\DemandeContact;
use App\Models\Tiers;
use App\Services\Integration\NotificationService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Campagnes e-mail / SMS / WhatsApp ciblées (remplace campagne, campagne par préférence et campagne externe).
 * Cible : rôles de tiers, canal d'acquisition, type de bien recherché, abonnés newsletter du site.
 * Les envois passent en file d'attente par les canaux configurés (Intégrations & messages), journalisés.
 */
class CampagneService
{
    /**
     * @return Collection<int, array{tiers_id: ?int, nom: string, email: ?string, telephone: ?string}>
     */
    public function destinataires(Campagne $campagne): Collection
    {
        $cible = $campagne->cible ?? [];
        $canalEmail = $campagne->canal === 'email';

        $tiers = Tiers::query()
            ->when(!empty($cible['roles']), fn ($q) => $q->avecRole(...$cible['roles']))
            ->when(!empty($cible['canal_acquisition']), fn ($q) => $q->where('canal_acquisition', $cible['canal_acquisition']))
            ->when(!empty($cible['type_bien']), fn ($q) => $q->whereHas('prospects', fn ($p) => $p->where('type_bien', $cible['type_bien'])))
            ->when($canalEmail, fn ($q) => $q->whereNotNull('email'), fn ($q) => $q->whereNotNull('telephone'))
            ->get()
            ->map(fn ($t) => ['tiers_id' => $t->id, 'nom' => $t->nom_complet, 'email' => $t->email, 'telephone' => $t->telephone]);

        if (!empty($cible['newsletter'])) {
            $abonnes = DemandeContact::where('newsletter', true)
                ->when($canalEmail, fn ($q) => $q->whereNotNull('email'), fn ($q) => $q->whereNotNull('telephone'))
                ->get()
                ->map(fn ($d) => ['tiers_id' => null, 'nom' => $d->nom, 'email' => $d->email, 'telephone' => $d->telephone]);
            $tiers = $tiers->concat($abonnes);
        }

        return $tiers->unique(fn ($d) => $canalEmail ? mb_strtolower((string) $d['email']) : preg_replace('/\D/', '', (string) $d['telephone']))->values();
    }

    public function envoyer(Campagne $campagne): int
    {
        if ($campagne->statut === 'envoyee') {
            throw new DomainException('Cette campagne a déjà été envoyée.');
        }

        $destinataires = $this->destinataires($campagne);
        if ($destinataires->isEmpty()) {
            throw new DomainException('Aucun destinataire ne correspond à la cible.');
        }

        DB::transaction(function () use ($campagne, $destinataires) {
            foreach ($destinataires as $destinataire) {
                $ligne = $campagne->destinataires()->create($destinataire + ['statut' => 'en_attente']);

                try {
                    $message = str_replace('{nom}', $destinataire['nom'], $campagne->message);
                    $campagne->canal === 'email'
                        ? NotificationService::enFile('EMAIL', $destinataire['email'], nl2br(e($message)), $campagne->objet ?: $campagne->nom, $destinataire['nom'], 'CAMPAGNE')
                        : NotificationService::enFile(strtoupper($campagne->canal), $destinataire['telephone'], $message, null, null, 'CAMPAGNE');
                    $ligne->update(['statut' => 'envoye', 'envoye_le' => now()]);
                } catch (\Throwable $e) {
                    report($e);
                    $ligne->update(['statut' => 'echec']);
                }
            }

            $campagne->update(['statut' => 'envoyee', 'envoyee_le' => now(), 'nb_destinataires' => $destinataires->count()]);
        });

        return $destinataires->count();
    }
}
