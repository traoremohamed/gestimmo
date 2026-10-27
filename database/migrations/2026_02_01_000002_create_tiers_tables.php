<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiers : une personne physique ou morale, avec un ou plusieurs rôles
 * (prospect, acquéreur, locataire, propriétaire, copropriétaire, fournisseur, garant).
 * Remplace la table client (≈70 colonnes) et ses pièces dupliquées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('type_personne', 10)->default('physique'); // physique | morale
            $table->string('civilite', 10)->nullable();
            $table->string('nom');
            $table->string('prenoms')->nullable();
            $table->string('raison_sociale')->nullable();
            $table->string('sexe', 1)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->foreignId('nationalite_id')->nullable()->constrained('pays')->nullOnDelete();
            $table->string('situation_matrimoniale', 50)->nullable();
            $table->string('regime_matrimonial', 50)->nullable();
            $table->string('profession')->nullable();
            $table->string('employeur')->nullable();
            $table->string('indicatif', 10)->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('telephone2', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('email2')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('boite_postale', 50)->nullable();
            $table->foreignId('pays_id')->nullable()->constrained('pays')->nullOnDelete();
            $table->string('rccm', 80)->nullable();
            $table->string('compte_contribuable', 80)->nullable();
            $table->string('representant_legal')->nullable();
            $table->string('corps_metier', 50)->nullable();   // fournisseurs
            $table->json('conjoint')->nullable();              // nom, date/lieu de naissance, profession, date/lieu de mariage
            $table->json('filiation')->nullable();             // père, mère
            $table->string('canal_acquisition', 50)->nullable();
            $table->foreignId('commercial_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // compte espace client
            $table->string('banque')->nullable();
            $table->string('rib', 60)->nullable();             // reversements propriétaires
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable(); // id_cli de l'ancienne application
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agence_id', 'code']);
            $table->index(['agence_id', 'nom']);
            $table->index(['agence_id', 'telephone']);
        });

        Schema::create('tiers_roles', function (Blueprint $table) {
            $table->foreignId('tiers_id')->constrained('tiers')->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->primary(['tiers_id', 'role']);
            $table->index('role');
        });

        Schema::create('tiers_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('tiers_id')->constrained('tiers')->cascadeOnDelete();
            $table->string('type_piece', 50);
            $table->string('numero', 100);
            $table->date('date_delivrance')->nullable();
            $table->date('date_expiration')->nullable();
            $table->string('lieu_delivrance')->nullable();
            $table->string('fichier')->nullable(); // disque privé
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiers_pieces');
        Schema::dropIfExists('tiers_roles');
        Schema::dropIfExists('tiers');
    }
};
