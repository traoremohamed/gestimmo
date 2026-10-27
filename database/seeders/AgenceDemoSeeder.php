<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\Pays;
use App\Models\User;
use App\Services\AgenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Agence de démonstration avec tous les modules et un directeur d'agence.
 *   identifiant : directeur.demo / mot de passe : Demo@2026!
 */
class AgenceDemoSeeder extends Seeder
{
    public function run(AgenceService $service): void
    {
        $agence = Agence::where('code', 'DEMO')->first() ?? $service->creer([
            'code' => 'DEMO',
            'raison_sociale' => 'Agence Immobilière de Démonstration',
            'sigle' => 'AID',
            'slug' => 'demo',
            'email' => 'contact@demo.immo',
            'telephone' => '0102030405',
            'ville' => 'Abidjan',
            'pays_id' => Pays::where('code', 'CI')->value('id'),
            'vitrine_active' => true,
        ]);

        $directeur = User::firstOrNew(['login_users' => 'directeur.demo']);
        if (!$directeur->exists) {
            $directeur->fill([
                'agence_id' => $agence->id,
                'name' => 'DIRECTEUR',
                'prenom_users' => 'Démo',
                'email' => 'directeur@demo.immo',
                'password' => Hash::make('Demo@2026!'),
                'flag_actif_users' => true,
                'flag_mdp' => true,
            ])->save();
        }
        $directeur->syncRoles(["DIRECTEUR D'AGENCE"]);
    }
}
