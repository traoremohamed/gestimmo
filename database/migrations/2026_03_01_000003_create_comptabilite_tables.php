<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptabilité SYSCOHADA alimentée automatiquement par les opérations (modèle GEST COMM) :
 *  cpt_comptes   : plan comptable de l'agence (initialisé depuis config/comptabilite.php)
 *  cpt_journaux  : journaux (CA caisse, BQ banque, MM mobile money, VT, LO, SY, PA paie, OD)
 *  cpt_schemas   : règles d'imputation paramétrables (opération + poste => compte, journal)
 *  cpt_exercices : exercices (ouvert / clôturé, résultat)
 *  cpt_ecritures : pièces équilibrées, rattachées à l'opération source (jamais supprimées : contre-passées)
 *  cpt_lignes    : lignes débit / crédit (tiers, lettrage)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpt_comptes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 20);
            $table->string('libelle');
            $table->unsignedTinyInteger('classe');
            $table->string('type', 20)->nullable();               // CAPITAUX | IMMOBILISATION | STOCK | TIERS | TRESORERIE | CHARGE | PRODUIT
            $table->boolean('lettrable')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
        });

        Schema::create('cpt_journaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('libelle');
            $table->string('type', 20)->default('OD');            // TRESORERIE | VENTE | ACHAT | PAIE | OD
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['agence_id', 'code']);
        });

        Schema::create('cpt_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('operation', 30);                      // ENCAISSEMENT | DEPENSE | REVERSEMENT | PAIE | AVANCE | TRESORERIE
            $table->string('cle', 40);                            // poste : echeance, loyer_mandat, ESPECES, TRAVAUX…
            $table->string('libelle');
            $table->string('compte', 20);
            $table->string('journal', 10)->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'operation', 'cle']);
        });

        Schema::create('cpt_exercices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('code', 10);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('statut', 10)->default('ouvert');      // ouvert | cloture
            $table->decimal('resultat', 15, 2)->nullable();
            $table->timestamp('cloture_le')->nullable();
            $table->foreignId('cloture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'code']);
        });

        Schema::create('cpt_ecritures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('exercice_id')->constrained('cpt_exercices')->restrictOnDelete();
            $table->foreignId('journal_id')->constrained('cpt_journaux')->restrictOnDelete();
            $table->string('numero', 40);
            $table->date('date_ecriture');
            $table->string('libelle');
            $table->string('piece', 60)->nullable();
            $table->string('operation', 30)->nullable();
            $table->nullableMorphs('source');
            $table->string('nature', 20)->default('normale');     // normale | contrepassation | a_nouveau | cloture | manuelle
            $table->foreignId('ecriture_origine_id')->nullable()->constrained('cpt_ecritures')->nullOnDelete();
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
            $table->index(['exercice_id', 'journal_id', 'date_ecriture']);
        });

        Schema::create('cpt_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecriture_id')->constrained('cpt_ecritures')->cascadeOnDelete();
            $table->foreignId('compte_id')->constrained('cpt_comptes')->restrictOnDelete();
            $table->string('libelle');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->foreignId('tiers_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->string('lettrage', 10)->nullable();
            $table->timestamps();

            $table->index(['compte_id', 'lettrage']);
        });
    }

    public function down(): void
    {
        foreach (['cpt_lignes', 'cpt_ecritures', 'cpt_exercices', 'cpt_schemas', 'cpt_journaux', 'cpt_comptes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
