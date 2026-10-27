<?php

namespace Tests\Concerns;

use App\Enums\StatutBien;
use App\Models\Agence;
use App\Models\Bien;
use App\Models\Tiers;
use App\Models\User;
use App\Services\AgenceService;
use App\Support\AgenceCourante;
use Database\Seeders\ImmobilierSeeder;
use Database\Seeders\RbacSeeder;

/**
 * Crée une agence, la définit comme agence courante et fournit des fabriques simples.
 */
trait AvecAgence
{
    protected Agence $agence;

    protected function creerAgence(string $code = 'TEST', ?array $modules = null): Agence
    {
        $agence = app(AgenceService::class)->creer([
            'code' => $code,
            'raison_sociale' => 'Agence ' . $code,
            'slug' => strtolower($code),
        ], $modules);

        app(AgenceCourante::class)->definir($agence);

        return $this->agence = $agence;
    }

    protected function seederRbac(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(ImmobilierSeeder::class);
    }

    protected function utilisateur(string $role = "DIRECTEUR D'AGENCE", ?Agence $agence = null): User
    {
        $user = User::create([
            'agence_id' => ($agence ?? $this->agence)->id,
            'name' => 'Agent',
            'prenom_users' => $role,
            'email' => uniqid() . '@test.ci',
            'login_users' => 'agent' . uniqid(),
            'password' => 'Secret@2026x',
            'flag_actif_users' => true,
            'flag_mdp' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function tiers(array $attributs = []): Tiers
    {
        static $n = 0;
        $n++;

        return Tiers::create($attributs + ['code' => 'T-' . $n . uniqid(), 'nom' => 'KOUASSI', 'prenoms' => 'Jean ' . $n, 'telephone' => '07000000' . $n]);
    }

    protected function bien(array $attributs = []): Bien
    {
        static $n = 0;
        $n++;

        return Bien::create($attributs + [
            'reference' => 'B-' . $n . uniqid(),
            'libelle' => 'Bien ' . $n,
            'type_bien' => 'VILLA',
            'statut' => StatutBien::Disponible,
        ]);
    }
}
