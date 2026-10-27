<?php

namespace App\Services;

use App\Models\Agence;
use App\Models\AgenceModule;
use App\Models\FraisType;
use App\Models\Referentiel;
use App\Services\Integration\NotificationService;
use App\Support\AgenceCourante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Création et initialisation d'une agence : modules, listes de valeurs et frais par défaut.
 */
class AgenceService
{
    /**
     * @param  string[]|null  $modules  modules à activer (null = tous)
     */
    public function creer(array $donnees, ?array $modules = null): Agence
    {
        return DB::transaction(function () use ($donnees, $modules) {
            $donnees['slug'] ??= Str::slug($donnees['sigle'] ?? $donnees['raison_sociale']);
            $donnees['code'] ??= strtoupper(Str::slug($donnees['sigle'] ?? $donnees['raison_sociale'], ''));

            $agence = Agence::create($donnees);
            $this->initialiser($agence, $modules ?? array_keys(config('immobilier.modules')));

            return $agence;
        });
    }

    /**
     * Idempotent : complète les valeurs manquantes sans écraser celles modifiées par l'agence.
     *
     * @param  string[]  $modules
     */
    public function initialiser(Agence $agence, array $modules = []): void
    {
        $this->synchroniserModules($agence, $modules, false);

        foreach (config('immobilier.referentiels') as $categorie => [, $valeurs]) {
            $ordre = 0;
            foreach ($valeurs as $code => $libelle) {
                Referentiel::toutesAgences()->firstOrCreate(
                    ['agence_id' => $agence->id, 'categorie' => $categorie, 'code' => $code],
                    ['libelle' => $libelle, 'ordre' => $ordre++, 'actif' => true]
                );
            }
        }

        foreach (config('immobilier.frais') as [$code, $libelle, $activite, $montant, $obligatoire, $document]) {
            FraisType::toutesAgences()->firstOrCreate(
                ['agence_id' => $agence->id, 'code' => $code],
                ['libelle' => $libelle, 'activite' => $activite, 'montant' => $montant, 'obligatoire' => $obligatoire, 'document_requis' => $document]
            );
        }

        $this->initialiserRecouvrementPaieCompta($agence);
        app(AgenceCourante::class)->pour($agence, fn () => NotificationService::initialiserModeles());

        Referentiel::oublier();
    }

    /**
     * Niveaux de relance, rubriques de paie (Côte d'Ivoire), plan comptable SYSCOHADA, journaux et règles d'imputation.
     */
    protected function initialiserRecouvrementPaieCompta(Agence $agence): void
    {
        $maintenant = now();
        $inserer = fn (string $table, array $lignes, array $unique) => DB::table($table)->insertOrIgnore(
            array_map(fn ($l) => $l + ['agence_id' => $agence->id, 'created_at' => $maintenant, 'updated_at' => $maintenant], $lignes)
        );

        $inserer('regles_relance', array_map(fn ($r) => [
            'niveau' => $r[0], 'libelle' => $r[1], 'delai_jours' => $r[2], 'canal' => $r[3], 'objet' => $r[4], 'message' => $r[5], 'automatique' => true, 'actif' => true,
        ], config('paie.relances')), ['niveau']);

        $inserer('rh_rubriques', array_map(fn ($r) => [
            'code' => $r[0], 'numero' => $r[1], 'libelle' => $r[2], 'type' => $r[3], 'mode_calcul' => $r[4], 'base' => $r[5], 'taux' => $r[6], 'montant' => $r[7],
            'plafond' => $r[8], 'franchise' => $r[9], 'imposable' => $r[10], 'cotisable' => $r[11], 'compte_charge' => $r[12], 'compte_tiers' => $r[13],
            'ordre' => $r[14], 'groupe' => $r[15], 'proratiser' => $r[16], 'actif' => true,
            'anciennete_min_ans' => $r[0] === 'ANCIEN' ? 2 : null,
            'bareme' => $r[0] === 'ITS' ? json_encode(config('paie.bareme_its')) : null,
            'reduction_parts' => $r[0] === 'ITS' ? json_encode(config('paie.ricf')) : null,
        ], config('paie.rubriques')), ['code']);

        $comptes = [];
        foreach (config('comptabilite.plan') as $numero => $c) {
            $comptes[] = ['numero' => (string) $numero, 'libelle' => $c[0], 'classe' => (int) substr((string) $numero, 0, 1), 'type' => $c[1], 'lettrable' => $c[2] ?? false, 'actif' => true];
        }
        $inserer('cpt_comptes', $comptes, ['numero']);

        $inserer('cpt_journaux', array_map(fn ($code, $j) => ['code' => $code, 'libelle' => $j[0], 'type' => $j[1], 'actif' => true],
            array_keys(config('comptabilite.journaux')), config('comptabilite.journaux')), ['code']);

        $schemas = [];
        foreach (config('comptabilite.schemas') as $operation => $regles) {
            foreach ($regles as $cle => [$libelle, $compte, $journal]) {
                $schemas[] = ['operation' => $operation, 'cle' => $cle, 'libelle' => $libelle, 'compte' => $compte, 'journal' => $journal];
            }
        }
        $inserer('cpt_schemas', $schemas, ['operation', 'cle']);
    }

    /**
     * @param  string[]  $modules  modules actifs souhaités
     * @param  bool  $desactiverAutres  désactive les modules non listés (écran « Mon agence »)
     */
    public function synchroniserModules(Agence $agence, array $modules, bool $desactiverAutres = true): void
    {
        foreach (array_keys(config('immobilier.modules')) as $code) {
            $actif = in_array($code, $modules, true) || (config("immobilier.modules.$code.obligatoire") ?? false);

            if (!$actif && !$desactiverAutres) {
                continue;
            }

            AgenceModule::updateOrCreate(['agence_id' => $agence->id, 'module' => $code], ['actif' => $actif]);
        }

        $agence->oublierModules();
    }
}
