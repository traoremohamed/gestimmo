<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion locative : mandats (propriétaire -> agence), baux, avis d'échéance
 * (loyers, dépôt de garantie, avance, frais d'agence, pénalités), états des lieux
 * et reversements aux propriétaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->foreignId('proprietaire_id')->constrained('tiers')->restrictOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->decimal('taux_commission', 5, 2)->default(0);
            $table->boolean('commission_sur_charges')->default(false);
            $table->unsignedTinyInteger('jour_reversement')->default(10);
            $table->string('statut', 20)->default('actif'); // actif | suspendu | resilie
            $table->text('conditions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agence_id', 'numero']);
        });

        Schema::table('biens', function (Blueprint $table) {
            $table->foreignId('mandat_id')->nullable()->after('proprietaire_id')->constrained('mandats')->nullOnDelete();
        });

        Schema::create('baux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40)->nullable();
            $table->foreignId('bien_id')->constrained('biens')->restrictOnDelete();
            $table->foreignId('locataire_id')->constrained('tiers')->restrictOnDelete();
            $table->foreignId('garant_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->foreignId('mandat_id')->nullable()->constrained('mandats')->nullOnDelete();
            $table->string('type_bail', 20)->default('habitation'); // habitation | professionnel | commercial
            $table->string('statut', 20)->default('brouillon');     // brouillon | actif | resilie | termine
            $table->date('date_debut');
            $table->unsignedSmallInteger('duree_mois')->default(12);
            $table->date('date_fin')->nullable();
            $table->boolean('tacite_reconduction')->default(true);
            $table->decimal('loyer_mensuel', 15, 2);
            $table->decimal('charges_mensuelles', 15, 2)->default(0);
            $table->unsignedTinyInteger('periodicite_mois')->default(1);
            $table->unsignedTinyInteger('jour_echeance')->default(5);
            $table->unsignedTinyInteger('mois_caution')->default(0);
            $table->unsignedTinyInteger('mois_avance')->default(0);
            $table->decimal('frais_agence', 15, 2)->default(0);
            $table->decimal('depot_garantie', 15, 2)->default(0);
            $table->decimal('taux_penalite', 5, 2)->default(0);
            $table->decimal('taux_revision', 5, 2)->default(0);
            $table->date('date_prochaine_revision')->nullable();
            $table->date('loyers_generes_jusqu_au')->nullable();
            $table->date('date_resiliation')->nullable();
            $table->string('motif_resiliation', 50)->nullable();
            $table->decimal('depot_restitue', 15, 2)->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agence_id', 'numero']);
            $table->index(['agence_id', 'statut']);
        });

        Schema::create('loyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('bail_id')->constrained('baux')->cascadeOnDelete();
            $table->string('nature', 20)->default('loyer'); // loyer | depot_garantie | avance | frais_agence | penalite | regularisation
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->string('libelle');
            $table->date('date_echeance');
            $table->decimal('montant_loyer', 15, 2)->default(0);
            $table->decimal('montant_charges', 15, 2)->default(0);
            $table->decimal('montant', 15, 2);           // total dû
            $table->decimal('montant_paye', 15, 2)->default(0);
            $table->string('statut', 20)->default('a_payer');
            $table->string('quittance_numero', 40)->nullable();
            $table->boolean('reverse')->default(false);  // inclus dans un reversement
            $table->timestamps();

            $table->index(['agence_id', 'statut', 'date_echeance']);
            $table->index(['bail_id', 'nature', 'periode_debut']);
        });

        Schema::create('etats_lieux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('bail_id')->constrained('baux')->cascadeOnDelete();
            $table->string('type', 10); // entree | sortie
            $table->date('date');
            $table->json('pieces')->nullable(); // [{piece, etat, commentaire}]
            $table->string('releves_compteurs')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reversements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->foreignId('proprietaire_id')->constrained('tiers')->restrictOnDelete();
            $table->foreignId('mandat_id')->nullable()->constrained('mandats')->nullOnDelete();
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->decimal('montant_encaisse', 15, 2)->default(0);
            $table->decimal('montant_commission', 15, 2)->default(0);
            $table->decimal('montant_deductions', 15, 2)->default(0); // travaux, dépenses refacturées
            $table->decimal('montant_net', 15, 2)->default(0);
            $table->string('statut', 20)->default('brouillon'); // brouillon | valide | paye
            $table->string('mode_paiement', 50)->nullable();
            $table->string('reference_paiement')->nullable();
            $table->timestamp('paye_le')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
        });

        Schema::create('reversement_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reversement_id')->constrained('reversements')->cascadeOnDelete();
            $table->foreignId('loyer_id')->nullable()->constrained('loyers')->nullOnDelete();
            $table->string('libelle');
            $table->decimal('montant_encaisse', 15, 2)->default(0);
            $table->decimal('taux_commission', 5, 2)->default(0);
            $table->decimal('montant_commission', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reversement_lignes', 'reversements', 'etats_lieux', 'loyers', 'baux'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('biens', fn (Blueprint $table) => $table->dropConstrainedForeignId('mandat_id'));
        Schema::dropIfExists('mandats');
    }
};
