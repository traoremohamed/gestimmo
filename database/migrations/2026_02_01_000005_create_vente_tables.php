<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventes : réservations (blocage temporaire), contrats, biens vendus,
 * frais annexes et échéancier. Les montants sont en decimal(15,2)
 * (ils étaient en varchar / double dans l'ancienne base).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40)->nullable(); // attribué à la validation
            $table->foreignId('acquereur_id')->constrained('tiers')->restrictOnDelete();
            $table->foreignId('commercial_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 20)->default('brouillon');
            $table->date('date_contrat')->nullable();
            $table->decimal('prix_total', 15, 2)->default(0);   // somme des biens
            $table->decimal('remise', 15, 2)->default(0);
            $table->decimal('montant_net', 15, 2)->default(0);  // prix_total - remise
            $table->decimal('montant_frais', 15, 2)->default(0);
            $table->string('mode_financement', 20)->default('echelonne'); // comptant | echelonne | credit
            $table->decimal('taux_acompte', 5, 2)->default(0);
            $table->decimal('montant_acompte', 15, 2)->default(0);
            $table->unsignedSmallInteger('nombre_echeances')->default(0);
            $table->unsignedSmallInteger('periodicite_mois')->default(1);
            $table->date('date_premiere_echeance')->nullable();
            $table->decimal('taux_commission', 5, 2)->default(0);
            $table->decimal('montant_commission', 15, 2)->default(0);
            $table->text('observation')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            // désistement / remboursement
            $table->date('date_desistement')->nullable();
            $table->string('motif_desistement', 50)->nullable();
            $table->text('observation_desistement')->nullable();
            $table->decimal('montant_retenu', 15, 2)->default(0);
            $table->decimal('montant_a_rembourser', 15, 2)->default(0);
            $table->timestamp('rembourse_le')->nullable();
            $table->foreignId('depense_remboursement_id')->nullable(); // contrainte ajoutée avec la table depenses
            $table->foreignId('vente_origine_id')->nullable()->constrained('ventes')->nullOnDelete(); // transfert
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agence_id', 'numero']);
            $table->index(['agence_id', 'statut']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->foreignId('tiers_id')->constrained('tiers')->restrictOnDelete();
            $table->foreignId('bien_id')->constrained('biens')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_reservation');
            $table->date('date_expiration');
            $table->string('statut', 20)->default('active'); // active | convertie | liberee | expiree
            $table->text('commentaire')->nullable();
            $table->string('motif_liberation')->nullable();
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
            $table->index(['bien_id', 'statut']);
        });

        Schema::create('vente_biens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('bien_id')->constrained('biens')->restrictOnDelete();
            $table->decimal('prix', 15, 2);
            $table->timestamps();

            $table->unique(['vente_id', 'bien_id']);
        });

        Schema::create('vente_frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('frais_type_id')->nullable()->constrained('frais_types')->nullOnDelete();
            $table->string('libelle');
            $table->decimal('montant', 15, 2)->default(0);
            $table->decimal('montant_paye', 15, 2)->default(0);
            $table->string('statut', 20)->default('a_payer'); // a_payer | partiel | paye | annule
            $table->boolean('document_requis')->default(false);
            $table->string('document')->nullable();               // disque privé
            $table->string('statut_document', 20)->nullable();    // attendu | disponible | notifie | remis
            $table->timestamp('document_notifie_le')->nullable();
            $table->timestamps();
        });

        Schema::create('echeances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero'); // 0 = acompte
            $table->string('libelle');
            $table->date('date_echeance');
            $table->decimal('montant', 15, 2);
            $table->decimal('montant_paye', 15, 2)->default(0);
            $table->string('statut', 20)->default('a_payer'); // a_payer | partiel | paye | annule
            $table->timestamps();

            $table->unique(['vente_id', 'numero']);
            $table->index(['agence_id', 'statut', 'date_echeance']);
        });
    }

    public function down(): void
    {
        foreach (['echeances', 'vente_frais', 'vente_biens', 'reservations', 'ventes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
