<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site vitrine paramétrable de chaque agence (règles de gestion reprises de MARIEATTIEKESAAS et du SIH) :
 *
 *  site_sites    : identité (titre, slogan, logo, favicon), couleurs, police, thème, coordonnées, réseaux, horaires, carte,
 *                  pied de page, référencement, CSS libre ; la publication reste portée par agences.vitrine_active
 *  site_pages    : pages (accueil unique à l'adresse racine, pages libres), présence au menu, publication, SEO, ordre
 *  site_sections : blocs ordonnés d'une page (config/site.php) : diaporama, recherche de biens, biens, programmes, services,
 *                  chiffres, équipe, témoignages, actualités, partenaires, galerie, FAQ, contact, carte…
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->unique()->constrained('agences')->cascadeOnDelete();
            $table->string('titre')->nullable();
            $table->string('slogan')->nullable();
            $table->text('description')->nullable();               // référencement
            $table->string('mots_cles')->nullable();
            $table->string('couleur_principale', 9)->default('#01619b');
            $table->string('couleur_secondaire', 9)->default('#f59e0b');
            $table->string('police', 60)->default('Inter');
            $table->string('theme', 10)->default('CLAIR');            // CLAIR, SOMBRE
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->jsonb('contact')->nullable();                     // telephone, whatsapp, email, adresse
            $table->jsonb('reseaux')->nullable();                     // facebook, instagram, tiktok, youtube, linkedin
            $table->text('horaires')->nullable();
            $table->text('carte_url')->nullable();
            $table->text('pied_page')->nullable();
            $table->text('css')->nullable();
            $table->boolean('lien_espace_client')->default(true);
            $table->boolean('menu_biens')->default(true);             // liens « Acheter / Louer » vers le catalogue
            $table->boolean('menu_actualites')->default(true);
            $table->timestamps();
        });

        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('site_sites')->cascadeOnDelete();
            $table->string('slug', 80);                               // vide = page d'accueil
            $table->string('titre');
            $table->boolean('est_accueil')->default(false);
            $table->boolean('dans_menu')->default(true);
            $table->boolean('publie')->default(true);
            $table->string('seo_titre')->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('site_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('site_pages')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('ancre', 60)->nullable();
            $table->boolean('dans_menu')->default(false);
            $table->string('titre')->nullable();
            $table->string('sous_titre')->nullable();
            $table->text('contenu')->nullable();
            $table->string('image')->nullable();
            $table->string('image_fond')->nullable();
            $table->string('couleur_fond', 9)->nullable();
            $table->boolean('sombre')->default(false);
            $table->jsonb('options')->nullable();
            $table->jsonb('elements')->nullable();
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['page_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_sections');
        Schema::dropIfExists('site_pages');
        Schema::dropIfExists('site_sites');
    }
};
