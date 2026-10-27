<?php

namespace Tests\Feature;

use App\Helpers\Audit;
use App\Helpers\Crypt;
use App\Helpers\Envoisms;
use App\Jobs\EnvoiMessageJob;
use App\Models\Logo;
use App\Models\Menus;
use App\Models\Permissions;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Test de bout en bout du module RBAC (menus ADMINISTRATION et PLATEFORME).
 *   php artisan test --filter=RbacModuleTest
 */
class RbacModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $motDePasse = 'Nouveau@2026x';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    protected function superAdmin(): User
    {
        $admin = User::where('login_users', config('rbac.super_admin.login'))->firstOrFail();
        $admin->forceFill(['flag_mdp' => true])->save();

        return $admin;
    }

    public function test_le_seeder_cree_administration_et_plateforme(): void
    {
        // l'ancien menu CONFIGURATION est éclaté, comme dans le SIH et MARIEATTIEKESAAS
        $this->assertFalse(Menus::where('menu', 'CONFIGURATION')->exists());
        $administration = Menus::where('code', 'ADMINISTRATION')->firstOrFail();
        $plateforme = Menus::where('code', 'PLATEFORME')->firstOrFail();
        $this->assertFalse((bool) $administration->flag_is_admin);
        $this->assertTrue((bool) $plateforme->flag_is_admin);

        $this->assertEqualsCanonicalizing(['users', 'roles', 'menuprofil', 'audits'], $administration->sousmenus()->pluck('sousmenu')->all());
        $this->assertEqualsCanonicalizing(['parametresysteme', 'menus', 'sousmenus', 'permissions'], $plateforme->sousmenus()->pluck('sousmenu')->all());
        $this->assertSame(20, Permissions::count());
        $this->assertTrue(Logo::actifs('LOGO')->exists());

        $spadmin = Role::where('codes_roles_detail', Role::SPADMIN)->firstOrFail();
        $this->assertSame(20, $spadmin->permissions()->count());
        $this->assertSame(8, $spadmin->sousmenus()->count());

        // relancer le seeder ne crée pas de doublons
        $this->seed(RbacSeeder::class);
        $this->assertSame(20, Permissions::count());
    }

    public function test_premiere_connexion_oblige_a_changer_le_mot_de_passe(): void
    {
        $this->post('/connexion', ['username' => config('rbac.super_admin.login'), 'password' => 'mauvais'])
            ->assertRedirect(route('connexion'));
        $this->assertGuest();

        $this->post('/connexion', ['username' => config('rbac.super_admin.login'), 'password' => config('rbac.super_admin.password')])
            ->assertRedirect(route('modifier.mot.passe'));
        $this->assertAuthenticated();

        $this->get('/dashboard')->assertRedirect(route('modifier.mot.passe'));

        // mot de passe trop faible
        $this->post('/modifiermotdepasse', ['cpwd' => config('rbac.super_admin.password'), 'npwd' => 'faible', 'vpwd' => 'faible'])
            ->assertSessionHas('error');

        $this->post('/modifiermotdepasse', [
            'cpwd' => config('rbac.super_admin.password'), 'npwd' => $this->motDePasse, 'vpwd' => $this->motDePasse,
        ])->assertRedirect(route('dashboard'));

        // le menu affiche les modules en casse de phrase (« Configuration »)
        $this->get('/dashboard')->assertOk()->assertSee('Plateforme')->assertSee('Identité de la plateforme');
        $this->assertDatabaseHas('historique_mot_de_passe', ['id_utilisateur' => auth()->id()]);
    }

    public function test_le_super_admin_accede_a_toutes_les_pages(): void
    {
        $this->actingAs($this->superAdmin());
        $role = Role::first();
        $logo = Logo::first();

        foreach ([
            '/dashboard', '/roles', '/roles/create', '/roles/' . Crypt::UrlCrypt($role->id) . '/edit',
            '/users', '/users/create', '/permissions', '/permissions/create',
            '/permissions/' . Crypt::UrlCrypt(Permissions::first()->id) . '/edit',
            '/menus', '/menus/create', '/menus/' . Menus::first()->id_menu . '/edit',
            '/sousmenus', '/sousmenus/create', '/menuprofil', '/menuprofillayout/' . $role->id,
            '/parametresysteme', '/creerparametresysteme', '/modifierparametresysteme/' . Crypt::UrlCrypt($logo->id_logo),
            '/audits', '/modifiermotdepasse',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    protected function agence(string $code): \App\Models\Agence
    {
        return \App\Models\Agence::create(['code' => $code, 'raison_sociale' => 'Agence ' . $code, 'slug' => strtolower($code), 'actif' => true]);
    }

    public function test_creation_profil_attribution_utilisateur_et_controle_acces(): void
    {
        Queue::fake();
        $agence = $this->agence('A');
        $this->actingAs($this->superAdmin());
        $this->post(route('agences.basculer'), ['agence_id' => $agence->id]);   // le super administrateur travaille dans l'agence

        // 1. création du profil : propre à l'agence
        $this->post('/roles', ['name' => 'GESTIONNAIRE'])->assertRedirect();
        $role = Role::where('name', 'GESTIONNAIRE')->firstOrFail();
        $this->assertSame($agence->id, $role->agence_id);

        // 2. attribution : sous-menu "Profils" + permission role-index uniquement
        $permission = Permissions::where('name', 'role-index')->firstOrFail();
        $this->post('/menuprofillayout/' . $role->id, [
            'route' => [$permission->id_sousmenu => (string) $permission->id_sousmenu],
            'permission' => [$permission->id => (string) $permission->id],
        ])->assertRedirect(route('menuprofil'));
        $this->assertTrue($role->fresh()->hasPermissionTo('role-index'));

        // 3. création d'un utilisateur avec ce profil -> mail + SMS en file d'attente
        $this->post('/users', [
            'roles' => 'GESTIONNAIRE/', 'login_users' => 'gestion01', 'name' => 'KOUASSI', 'prenom_users' => 'Awa',
            'password' => 'Provisoire@1', 'email' => 'awa@example.com', 'cel_users' => '0102030405', 'flag_actif_users' => 'true',
        ])->assertRedirect(route('users.index'));

        $user = User::where('login_users', 'gestion01')->firstOrFail();
        $this->assertTrue($user->hasRole('GESTIONNAIRE'));
        $this->assertSame($agence->id, $user->agence_id);   // créé dans l'agence de travail
        Queue::assertPushed(EnvoiMessageJob::class, fn ($j) => $j->canal === 'EMAIL' && $j->destinataire === 'awa@example.com');
        Queue::assertPushed(EnvoiMessageJob::class, fn ($j) => $j->canal === 'SMS' && $j->destinataire === '0102030405');

        // 4. contrôle d'accès
        $user->forceFill(['flag_mdp' => true])->save();
        $this->actingAs($user);
        $this->get('/roles')->assertOk();
        $this->get('/users')->assertForbidden();
        $this->get('/parametresysteme')->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertSee('Profils')->assertDontSee('Identité de la plateforme');

        // 5. cloisonnement : un directeur d'une autre agence ne voit ni ce profil ni cet utilisateur
        $autre = $this->agence('B');
        $directeurB = User::factory()->create(['login_users' => 'directeurb', 'flag_mdp' => true, 'flag_actif_users' => true, 'agence_id' => $autre->id]);
        $directeurB->assignRole('ADMINISTRATEUR');
        $this->actingAs($directeurB);
        $this->get('/roles')->assertOk()->assertDontSee('GESTIONNAIRE');
        $this->get('/users')->assertOk()->assertDontSee('gestion01');
        $this->get('/users/' . Crypt::UrlCrypt($user->id) . '/edit')->assertNotFound();
        $this->get('/menuprofillayout/' . Crypt::UrlCrypt($role->id))->assertNotFound();
        $this->get('/permissions')->assertForbidden();                   // structure des menus : plateforme uniquement
    }

    public function test_verrouillage_apres_echecs_et_periode_d_acces(): void
    {
        $agence = $this->agence('V');
        $user = User::factory()->create(['login_users' => 'verrou001', 'password' => 'Secret@2026x', 'flag_mdp' => true, 'flag_actif_users' => true, 'agence_id' => $agence->id]);
        $user->assignRole('ADMINISTRATEUR');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['username' => 'verrou001', 'password' => 'mauvais']);
        }
        $this->assertNotNull($user->fresh()->verrouille_jusqu_a);
        $this->post('/connexion', ['username' => 'verrou001', 'password' => 'Secret@2026x'])->assertSessionHas('error');
        $this->assertGuest();

        // déverrouillé ; mais période d'accès échue : refus
        $user->forceFill(['verrouille_jusqu_a' => null, 'tentatives_echouees' => 0, 'date_fin_acces' => today()->subDay()])->save();
        $this->post('/connexion', ['username' => 'verrou001', 'password' => 'Secret@2026x'])->assertSessionHas('error');
        $this->assertGuest();

        // accès rétabli : connexion par e-mail possible, dernière connexion enregistrée
        $user->forceFill(['date_fin_acces' => null])->save();
        $this->post('/connexion', ['username' => $user->email, 'password' => 'Secret@2026x'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->derniere_connexion_at);
    }

    public function test_un_non_spadmin_ne_voit_pas_les_profils_spadmin(): void
    {
        $admin = User::factory()->create(['login_users' => 'adminsimple', 'flag_mdp' => true, 'flag_actif_users' => true, 'agence_id' => $this->agence('S')->id]);
        $admin->assignRole('ADMINISTRATEUR');
        $this->actingAs($admin);

        $this->get('/roles')->assertOk()->assertDontSee('SUPER ADMINISTRATEUR')->assertSee('ADMINISTRATEUR');
        $spadmin = Role::where('codes_roles_detail', Role::SPADMIN)->first();
        $this->get('/menuprofillayout/' . Crypt::UrlCrypt($spadmin->id))->assertNotFound();   // jamais proposé à une agence
    }

    public function test_parametres_systeme_un_seul_logo_actif(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/creerparametresysteme', ['valeur' => 'LOGO', 'titre_logo' => 'Second', 'mot_cle' => 'X', 'flag_logo' => 'on'])
            ->assertRedirect(route('parametresysteme'));

        $second = Logo::where('titre_logo', 'Second')->firstOrFail();
        $this->assertFalse($second->flag_logo);
        $this->assertSame(1, Logo::actifs('LOGO')->count());

        $this->get('/activeparametresysteme/' . Crypt::UrlCrypt($second->id_logo) . '/' . Crypt::UrlCrypt('LOGO'))
            ->assertSessionHas('error');

        // COULEUR MENU HAUT : on ne peut pas désactiver la dernière valeur
        $couleur = Logo::actifs('COULEUR MENU HAUT')->firstOrFail();
        $this->get('/desactiveparametresysteme/' . Crypt::UrlCrypt($couleur->id_logo) . '/' . Crypt::UrlCrypt($couleur->valeur))
            ->assertSessionHas('error');
        $this->assertTrue($couleur->fresh()->flag_logo);
    }

    public function test_audit_ecrit_dans_le_schema_separe(): void
    {
        $this->actingAs($this->superAdmin());
        $this->post('/menus', ['menu' => 'TEST AUDIT', 'priorite_menu' => 9, 'is_valide' => 'on'])->assertRedirect();

        $this->assertSame('audit.log', Audit::table());
        $ligne = DB::table(Audit::table())->where('menu_log', 'LISTE DES MODULES')->latest('id_log')->first();
        $this->assertNotNull($ligne);
        $this->assertSame('CREER', $ligne->action_log);
        $this->assertSame(config('rbac.super_admin.login'), $ligne->identifiant_log);
        $this->assertSame('SUPER ADMINISTRATEUR', $ligne->role_log);

        $this->get('/audits?module=MODULES')->assertOk()->assertSee('LISTE DES MODULES');
    }

    public function test_envoi_sms_driver_log(): void
    {
        config(['rbac.sms.driver' => 'log']);

        $this->assertSame('2250102030405', Envoisms::formatNumero('01 02 03 04 05'));
        $this->assertSame('2250102030405', Envoisms::formatNumero('2250102030405'));
        $this->assertTrue(Envoisms::sendSMS('0102030405', 'Bonjour'));
    }
}
