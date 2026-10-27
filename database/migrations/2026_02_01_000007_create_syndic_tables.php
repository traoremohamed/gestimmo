<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Syndic de copropriété : copropriétés, lots et tantièmes, clés de répartition,
 * budgets prévisionnels, appels de fonds, assemblées générales et résolutions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coproprietes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('nom');
            $table->foreignId('immeuble_id')->nullable()->constrained('immeubles')->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->string('adresse')->nullable();
            $table->string('immatriculation')->nullable();
            $table->date('mandat_debut')->nullable();
            $table->date('mandat_fin')->nullable();
            $table->decimal('honoraires_annuels', 15, 2)->default(0);
            $table->unsignedInteger('total_tantiemes')->default(1000);
            $table->decimal('taux_fonds_travaux', 5, 2)->default(0);
            $table->unsignedTinyInteger('exercice_debut_mois')->default(1);
            $table->string('banque')->nullable();
            $table->string('rib', 60)->nullable();
            $table->string('statut', 20)->default('active');
            $table->text('reglement')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('copro_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copropriete_id')->constrained('coproprietes')->cascadeOnDelete();
            $table->foreignId('bien_id')->nullable()->constrained('biens')->nullOnDelete();
            $table->string('numero', 30);
            $table->string('designation')->nullable();
            $table->foreignId('coproprietaire_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->foreignId('occupant_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->decimal('tantiemes', 12, 4); // clé « charges générales »
            $table->timestamps();

            $table->unique(['copropriete_id', 'numero']);
        });

        // clés spéciales (ascenseur, escalier B, parking…) ; les charges générales utilisent copro_lots.tantiemes
        Schema::create('cles_repartition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copropriete_id')->constrained('coproprietes')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('libelle');
            $table->timestamps();

            $table->unique(['copropriete_id', 'code']);
        });

        Schema::create('cle_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cle_repartition_id')->constrained('cles_repartition')->cascadeOnDelete();
            $table->foreignId('copro_lot_id')->constrained('copro_lots')->cascadeOnDelete();
            $table->decimal('tantiemes', 12, 4);
            $table->timestamps();

            $table->unique(['cle_repartition_id', 'copro_lot_id']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copropriete_id')->constrained('coproprietes')->cascadeOnDelete();
            $table->unsignedSmallInteger('exercice');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('statut', 20)->default('brouillon'); // brouillon | vote | cloture
            $table->decimal('montant_total', 15, 2)->default(0);
            $table->date('vote_le')->nullable();
            $table->timestamps();

            $table->unique(['copropriete_id', 'exercice']);
        });

        Schema::create('budget_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('poste', 50); // referentiel categorie_depense
            $table->string('libelle')->nullable();
            $table->foreignId('cle_repartition_id')->nullable()->constrained('cles_repartition')->nullOnDelete(); // null = générales
            $table->decimal('montant', 15, 2);
            $table->timestamps();
        });

        Schema::create('appels_fonds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('copropriete_id')->constrained('coproprietes')->cascadeOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained('budgets')->nullOnDelete();
            $table->string('numero', 40)->nullable();
            $table->string('type', 20)->default('provision'); // provision | travaux | exceptionnel | fonds_travaux
            $table->string('libelle');
            $table->foreignId('cle_repartition_id')->nullable()->constrained('cles_repartition')->nullOnDelete();
            $table->date('date_appel');
            $table->date('date_echeance');
            $table->decimal('montant_total', 15, 2);
            $table->string('statut', 20)->default('brouillon'); // brouillon | emis | solde | annule
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
        });

        Schema::create('appel_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('appel_fonds_id')->constrained('appels_fonds')->cascadeOnDelete();
            $table->foreignId('copro_lot_id')->constrained('copro_lots')->restrictOnDelete();
            $table->foreignId('coproprietaire_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->decimal('tantiemes', 12, 4);
            $table->decimal('montant', 15, 2);
            $table->decimal('montant_paye', 15, 2)->default(0);
            $table->string('statut', 20)->default('a_payer');
            $table->timestamps();

            $table->index(['agence_id', 'statut']);
        });

        Schema::create('assemblees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('copropriete_id')->constrained('coproprietes')->cascadeOnDelete();
            $table->string('type', 20)->default('ordinaire'); // ordinaire | extraordinaire
            $table->dateTime('date_heure');
            $table->string('lieu')->nullable();
            $table->text('ordre_du_jour')->nullable();
            $table->string('statut', 20)->default('planifiee'); // planifiee | convoquee | tenue | annulee
            $table->decimal('tantiemes_presents', 12, 4)->nullable();
            $table->text('proces_verbal')->nullable();
            $table->timestamp('convoquee_le')->nullable();
            $table->timestamps();
        });

        Schema::create('resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assemblee_id')->constrained('assemblees')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('intitule');
            $table->text('description')->nullable();
            $table->string('majorite', 20)->default('simple'); // simple | absolue | double | unanimite
            $table->decimal('voix_pour', 12, 4)->default(0);
            $table->decimal('voix_contre', 12, 4)->default(0);
            $table->decimal('voix_abstention', 12, 4)->default(0);
            $table->boolean('adoptee')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['resolutions', 'assemblees', 'appel_lignes', 'appels_fonds', 'budget_lignes', 'budgets', 'cle_lots', 'cles_repartition', 'copro_lots', 'coproprietes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
