<?php

namespace Tests\Feature\Metier;

use App\Helpers\Menu;
use App\Models\Systeme\SauvegardeConfig;
use App\Models\Systeme\SauvegardeDestination;
use App\Models\User;
use App\Support\AgenceCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

/**
 * Menus ADMINISTRATION / PARAMETRAGE / PLATEFORME (comme le SIH et MARIEATTIEKESAAS) :
 * tableau de bord plateforme, sécurité, sauvegardes, modules activés.
 */
class AdministrationPlateformeTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seederRbac();
        $this->creerAgence('ADM');
    }

    protected function superAdmin(): User
    {
        $admin = User::where('login_users', config('rbac.super_admin.login'))->firstOrFail();
        $admin->forceFill(['flag_mdp' => true])->save();

        return $admin;
    }

    public function test_menus_hors_agence_et_dans_l_agence(): void
    {
        $admin = $this->superAdmin();
        app(AgenceCourante::class)->definir(null);

        $codes = fn () => collect(Menu::arbre($admin->id))->pluck('code')->all();
        $this->assertEqualsCanonicalizing(['ADMINISTRATION', 'PARAMETRAGE', 'PLATEFORME'], $codes());
        $administration = collect(Menu::arbre($admin->id))->firstWhere('code', 'ADMINISTRATION');
        $this->assertNotContains('monagence', array_column($administration['noeuds'], 'lien'), 'écran d\'agence masqué hors agence');

        // un directeur d'agence ne voit jamais la plateforme
        $directeur = $this->utilisateur();
        app(AgenceCourante::class)->definir($this->agence);
        Menu::oublierArbres();
        $arbre = collect(Menu::arbre($directeur->id));
        $this->assertNull($arbre->firstWhere('code', 'PLATEFORME'));
        $this->assertContains('configuration/modules', array_column($arbre->firstWhere('code', 'ADMINISTRATION')['noeuds'], 'lien'));
    }

    public function test_ecrans_plateforme_reserves_au_super_administrateur(): void
    {
        $admin = $this->superAdmin();
        $directeur = $this->utilisateur();
        app(AgenceCourante::class)->definir(null);

        foreach (['plateforme.tableau', 'sauvegardes.index', 'securite.index'] as $route) {
            $this->actingAs($directeur)->get(route($route))->assertForbidden();
        }
        $this->actingAs($admin)->get(route('plateforme.tableau'))->assertOk()->assertSee('Agence ADM')->assertSee('Score de sécurité');
        $this->actingAs($admin)->get(route('securite.index'))->assertOk()->assertSee('Mode débogage désactivé');
        $this->actingAs($admin)->get(route('sauvegardes.index'))->assertOk()->assertSee('Procédure de restauration');
    }

    public function test_configuration_des_sauvegardes_et_destination(): void
    {
        $admin = $this->superAdmin();
        app(AgenceCourante::class)->definir(null);

        $this->actingAs($admin)->post(route('sauvegardes.configurer'), ['actif' => 1, 'frequence' => 'QUOTIDIENNE', 'jour_semaine' => 7, 'heure' => '03:30',
            'inclure_base' => 1, 'retention_locale' => 5, 'cle_chiffrement' => 'Cle-Tres-Secrete-2026', 'cle_chiffrement_confirmation' => 'Cle-Tres-Secrete-2026'])
            ->assertSessionHas('success');
        $config = SauvegardeConfig::courante();
        $this->assertTrue($config->actif);
        $this->assertSame('Cle-Tres-Secrete-2026', $config->cle());
        $this->assertStringNotContainsString('Cle-Tres-Secrete', (string) $config->getRawOriginal('cle_chiffrement'), 'clé chiffrée en base');

        $dossier = storage_path('framework/testing/sauvegardes');
        $this->actingAs($admin)->post(route('sauvegardes.destinations.store'), ['libelle' => 'NAS', 'type' => 'LOCAL', 'retention' => 3, 'parametres' => ['chemin' => $dossier]])
            ->assertSessionHas('success');
        $destination = SauvegardeDestination::firstOrFail();
        $this->actingAs($admin)->post(route('sauvegardes.destinations.tester', $destination))->assertSessionHas('success');
        $this->assertStringStartsWith('OK', $destination->refresh()->dernier_test_resultat);
    }

    public function test_modules_actives_par_l_agence(): void
    {
        $directeur = $this->utilisateur();
        app(AgenceCourante::class)->definir(null);

        $this->actingAs($directeur)->get(route('modules.index'))->assertOk()->assertSee('Modules activés');
        $this->actingAs($directeur)->put(route('modules.update'), ['modules' => ['vente']])->assertSessionHas('success');

        $actifs = $this->agence->refresh()->modulesActifs();
        $this->assertContains('vente', $actifs);
        $this->assertNotContains('location', $actifs);
        $this->assertContains('patrimoine', $actifs, 'module socle toujours actif');

        // l'identité de l'agence ne touche plus aux modules
        $this->actingAs($directeur)->put(route('monagence.update'), ['raison_sociale' => 'Agence ADM', 'couleur' => '#01619b', 'devise' => 'XOF'])->assertRedirect();
        $this->assertContains('vente', $this->agence->refresh()->modulesActifs());
    }
}
