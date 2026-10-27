<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Socle SaaS : agences (locataires de la plateforme), modules activés,
 * paramètres, listes de valeurs, numérotation, frais annexes, médias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pays', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->nullable()->unique();
            $table->string('libelle');
            $table->string('nationalite')->nullable();
            $table->string('indicatif', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('agences', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('raison_sociale');
            $table->string('sigle', 50)->nullable();
            $table->string('slug', 80)->unique();           // sous-chemin du site vitrine : /site/{slug}
            $table->string('domaine')->nullable()->unique(); // domaine personnalisé du site vitrine
            $table->string('email')->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('telephone2', 50)->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->foreignId('pays_id')->nullable()->constrained('pays')->nullOnDelete();
            $table->string('rccm', 80)->nullable();
            $table->string('compte_contribuable', 80)->nullable();
            $table->string('logo')->nullable();
            $table->string('couleur', 7)->default('#01619b');
            $table->string('devise', 10)->default('FCFA');
            $table->string('plan', 30)->nullable();          // offre d'abonnement
            $table->date('abonnement_fin')->nullable();
            $table->boolean('vitrine_active')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('agence_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('module', 30);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['agence_id', 'module']);
        });

        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('cle', 80);
            $table->text('valeur')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'cle']);
        });

        Schema::create('referentiels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('categorie', 50);
            $table->string('code', 50);
            $table->string('libelle');
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'categorie', 'code']);
            $table->index(['agence_id', 'categorie', 'actif']);
        });

        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('cle', 50);
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamps();

            $table->unique(['agence_id', 'cle', 'annee']);
        });

        Schema::create('frais_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('libelle');
            $table->string('activite', 20); // vente | location | syndic | meuble
            $table->string('mode_calcul', 20)->default('fixe'); // fixe | pourcentage
            $table->decimal('montant', 15, 2)->default(0);      // montant ou taux
            $table->boolean('obligatoire')->default(false);
            $table->boolean('document_requis')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['agence_id', 'code']);
        });

        Schema::create('medias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->morphs('mediable');
            $table->string('collection', 30)->default('photos'); // photos | plans | videos | documents
            $table->string('disque', 20);
            $table->string('chemin');
            $table->string('nom_original')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('taille')->nullable();
            $table->string('libelle')->nullable();
            $table->integer('ordre')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // null = utilisateur de la plateforme (super administrateur)
            $table->foreignId('agence_id')->nullable()->after('id')->constrained('agences')->nullOnDelete();
        });

        Schema::table('menu', function (Blueprint $table) {
            // module métier auquel appartient le menu (null = menus du socle, toujours visibles)
            $table->string('module', 30)->nullable()->after('menu');
        });
    }

    public function down(): void
    {
        Schema::table('menu', fn (Blueprint $table) => $table->dropColumn('module'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('agence_id'));

        foreach (['medias', 'frais_types', 'sequences', 'referentiels', 'parametres', 'agence_modules', 'agences', 'pays'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
