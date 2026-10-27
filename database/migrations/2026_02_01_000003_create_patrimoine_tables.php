<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patrimoine : programmes (anciens « sites »), lotissements, immeubles et biens.
 * Un bien unique remplace lot / bien_location / bien_a_generer / bienagenererlocation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('nom');
            $table->string('type', 20)->default('mixte'); // lotissement | residence | immeuble | mixte
            $table->string('ville')->nullable();
            $table->string('commune')->nullable();
            $table->string('adresse')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('date_lancement')->nullable();
            $table->date('date_livraison_prevue')->nullable();
            $table->text('description')->nullable();
            $table->boolean('publie')->default(false);
            $table->boolean('actif')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lotissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->string('nom');
            $table->string('reference')->nullable();
            $table->string('type_titre', 30)->nullable();
            $table->string('numero_titre')->nullable();
            $table->string('prefecture')->nullable();
            $table->string('village')->nullable();
            $table->decimal('superficie', 14, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('immeubles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->foreignId('lotissement_id')->nullable()->constrained('lotissements')->nullOnDelete();
            $table->foreignId('proprietaire_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->string('nom');
            $table->string('ilot', 50)->nullable();
            $table->string('lot', 50)->nullable();
            $table->string('adresse')->nullable();
            $table->unsignedSmallInteger('nombre_etages')->default(0);
            $table->decimal('superficie', 14, 2)->nullable();
            $table->unsignedSmallInteger('annee_construction')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('biens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('reference', 40);
            $table->string('libelle');
            $table->string('type_bien', 50);
            $table->string('usage', 20)->default('habitation'); // habitation | professionnel | mixte
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->foreignId('lotissement_id')->nullable()->constrained('lotissements')->nullOnDelete();
            $table->foreignId('immeuble_id')->nullable()->constrained('immeubles')->nullOnDelete();
            $table->foreignId('proprietaire_id')->nullable()->constrained('tiers')->nullOnDelete(); // mandant (gestion locative)
            $table->string('ilot', 50)->nullable();
            $table->string('numero_lot', 50)->nullable();
            $table->string('etage', 20)->nullable();
            $table->string('porte', 20)->nullable();
            $table->decimal('superficie', 14, 2)->nullable();
            $table->string('unite_superficie', 5)->default('m2'); // m2 | ha
            $table->unsignedSmallInteger('nb_pieces')->nullable();
            $table->unsignedSmallInteger('nb_chambres')->nullable();
            $table->unsignedSmallInteger('nb_salons')->nullable();
            $table->unsignedSmallInteger('nb_salles_bain')->nullable();
            $table->unsignedSmallInteger('nb_toilettes')->nullable();
            $table->unsignedSmallInteger('nb_cuisines')->nullable();
            $table->json('equipements')->nullable();
            $table->json('caracteristiques')->nullable(); // attributs libres paramétrables
            // destinations : un bien peut être à vendre et/ou à louer et/ou en meublé
            $table->boolean('a_vendre')->default(false);
            $table->boolean('a_louer')->default(false);
            $table->boolean('meuble')->default(false);
            $table->decimal('prix_vente', 15, 2)->nullable();
            $table->decimal('loyer_mensuel', 15, 2)->nullable();
            $table->decimal('charges_mensuelles', 15, 2)->nullable();
            $table->decimal('prix_nuitee', 15, 2)->nullable();
            $table->unsignedSmallInteger('delai_livraison_mois')->nullable();
            $table->string('statut', 20)->default('disponible');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('publie')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agence_id', 'reference']);
            $table->index(['agence_id', 'statut']);
            $table->index(['agence_id', 'type_bien']);
        });
    }

    public function down(): void
    {
        foreach (['biens', 'immeubles', 'lotissements', 'programmes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
