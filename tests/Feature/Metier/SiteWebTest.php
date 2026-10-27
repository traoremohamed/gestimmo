<?php

namespace Tests\Feature\Metier;

use App\Models\Site\Page;
use App\Models\Site\Section;
use App\Models\Site\Site;
use App\Support\AgenceCourante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\AvecAgence;
use Tests\TestCase;

/**
 * Site vitrine paramétrable : modèle immobilier, pages, blocs, adresses réservées, nettoyage, aperçu et publication.
 */
class SiteWebTest extends TestCase
{
    use AvecAgence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seederRbac();
        $this->creerAgence('WEB');
    }

    public function test_modele_puis_blocs_et_pages(): void
    {
        $directeur = $this->utilisateur();
        $this->bien(['a_vendre' => true, 'prix_vente' => 30000000, 'publie' => true, 'libelle' => 'Duplex du modèle']);
        app(AgenceCourante::class)->definir(null);

        $this->actingAs($directeur)->get(route('siteweb.index'))->assertOk()->assertSee('Créer le site à partir du modèle');
        $this->actingAs($directeur)->post(route('siteweb.modele'))->assertRedirect();

        $accueil = Page::withoutGlobalScopes()->where('agence_id', $this->agence->id)->where('est_accueil', true)->firstOrFail();
        $this->assertGreaterThan(5, Section::withoutGlobalScopes()->where('page_id', $accueil->id)->count());
        $this->actingAs($directeur)->get(route('siteweb.pages.edit', $accueil))->assertOk();
        $this->actingAs($directeur)->get(route('siteweb.apercu'))->assertOk()->assertSee('Duplex du modèle');

        // page libre : une adresse réservée (« contact ») est détournée
        $this->actingAs($directeur)->post(route('siteweb.pages.store'), ['titre' => 'Contact'])->assertRedirect();
        $page = Page::withoutGlobalScopes()->where('agence_id', $this->agence->id)->where('est_accueil', false)->latest('id')->firstOrFail();
        $this->assertNotContains($page->slug, config('site.slugs_reserves'));

        // bloc texte : les scripts et gestionnaires d'événements sont retirés
        $this->actingAs($directeur)->post(route('siteweb.sections.store', $page), ['type' => 'TEXTE_IMAGE'])->assertRedirect();
        $bloc = Section::withoutGlobalScopes()->where('page_id', $page->id)->firstOrFail();
        $this->actingAs($directeur)->get(route('siteweb.sections.edit', $bloc))->assertOk();
        $this->actingAs($directeur)->put(route('siteweb.sections.update', $bloc), ['titre' => 'Notre histoire',
            'contenu' => '<p onclick="x()">Depuis 2005</p><script>alert(1)</script><a href="javascript:alert(1)">lien</a>'])->assertRedirect();
        $bloc->refresh();
        $this->assertStringContainsString('Depuis 2005', $bloc->contenu);
        $this->assertStringNotContainsString('script', $bloc->contenu);
        $this->assertStringNotContainsString('onclick', $bloc->contenu);
        $this->assertStringNotContainsString('javascript:', $bloc->contenu);

        $this->actingAs($directeur)->post(route('siteweb.sections.visibilite', $bloc));
        $this->assertFalse($bloc->refresh()->visible);

        // la page d'accueil ne se supprime pas, une page libre si
        $this->actingAs($directeur)->delete(route('siteweb.pages.destroy', $accueil))->assertStatus(422);
        $this->actingAs($directeur)->delete(route('siteweb.pages.destroy', $page))->assertRedirect(route('siteweb.index'));
    }

    public function test_publication_et_site_public(): void
    {
        $directeur = $this->utilisateur();
        app(AgenceCourante::class)->definir(null);
        $slug = $this->agence->slug;

        $this->actingAs($directeur)->put(route('siteweb.reglages'), ['titre' => 'Immo Lagune', 'slogan' => 'Votre toit, notre métier',
            'couleur_principale' => '#0d6efd', 'couleur_secondaire' => '#198754', 'police' => config('site.polices')[0], 'theme' => 'CLAIR'])->assertSessionHas('success');
        $this->actingAs($directeur)->post(route('siteweb.publier'), ['publie' => 1])->assertSessionHas('error');
        $this->actingAs($directeur)->post(route('siteweb.modele'));
        $this->actingAs($directeur)->post(route('siteweb.publier'), ['publie' => 1])->assertSessionHas('success');
        $this->assertTrue((bool) $this->agence->refresh()->vitrine_active);

        auth()->logout();
        app(AgenceCourante::class)->definir(null);
        $this->get(route('vitrine.accueil', $slug))->assertOk()->assertSee('Immo Lagune')->assertSee('Votre toit, notre métier');
        $this->get(route('vitrine.biens', $slug))->assertOk();
        $this->get(route('vitrine.contact', $slug))->assertOk();
    }

    public function test_une_agence_ne_touche_pas_au_site_d_une_autre(): void
    {
        $agence = $this->agence;
        $autre = $this->creerAgence('AUTRE');
        $directeur = $this->utilisateur(agence: $agence);
        app(AgenceCourante::class)->definir($autre);
        $site = Site::create(['titre' => 'Autre site']);
        $page = Page::create(['site_id' => $site->id, 'titre' => 'Accueil', 'slug' => '', 'est_accueil' => true, 'publie' => true, 'dans_menu' => true]);
        app(AgenceCourante::class)->definir(null);

        $this->actingAs($directeur)->get(route('siteweb.pages.edit', $page->id))->assertNotFound();
        $this->actingAs($directeur)->delete(route('siteweb.pages.destroy', $page->id))->assertNotFound();
    }

    public function test_droits_lecture_seule(): void
    {
        $agent = $this->utilisateur('CAISSIER / COMPTABLE');
        app(AgenceCourante::class)->definir(null);

        $this->actingAs($agent)->post(route('siteweb.modele'))->assertForbidden();
        $this->actingAs($agent)->post(route('siteweb.publier'), ['publie' => 1])->assertForbidden();
    }
}
