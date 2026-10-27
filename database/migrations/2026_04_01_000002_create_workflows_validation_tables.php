<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moteur générique de workflows de validation (repris de MARIEATTIEKESAAS / SIH), par agence :
 *
 *  - wf_circuits   : circuit paramétré pour une action du catalogue (App\Services\Validation\Catalogue), filtre optionnel,
 *                    montant de déclenchement ; gouvernance BROUILLON → APPROUVE (par un autre utilisateur) → ACTIF → RETIRE, versions
 *  - wf_etapes     : étapes ordonnées : profil et / ou permission du validateur, plage de montant, délai, validateur ≠ auteur
 *  - wf_demandes   : action soumise, mise en attente jusqu'à la dernière approbation puis exécutée automatiquement
 *  - wf_decisions  : décision de chaque étape (qui, quand, commentaire) — historique non modifiable
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wf_circuits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('circuit_precedent_id')->nullable()->constrained('wf_circuits')->nullOnDelete();
            $table->string('action', 60);
            $table->string('filtre', 60)->nullable();
            $table->decimal('montant_declenchement', 15, 2)->nullable();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('statut', 20)->default('BROUILLON');        // BROUILLON, APPROUVE, ACTIF, RETIRE
            $table->foreignId('approuve_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approuve_at')->nullable();
            $table->timestamp('active_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        // une version par action et par filtre (filtre vide compris) : index sur COALESCE, compatible PostgreSQL < 15
        DB::statement("create unique index wf_circuits_agence_action_filtre_version_unique on wf_circuits (agence_id, action, coalesce(filtre, ''), version)");

        Schema::create('wf_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circuit_id')->constrained('wf_circuits')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordre');
            $table->string('libelle');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('permission_requise', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // validateur nommé
            $table->decimal('montant_min', 15, 2)->nullable();
            $table->decimal('montant_max', 15, 2)->nullable();
            $table->boolean('separation_auteur')->default(true);
            $table->unsignedSmallInteger('delai_heures')->nullable();
            $table->timestamps();

            $table->unique(['circuit_id', 'ordre']);
        });

        Schema::create('wf_demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 30);
            $table->foreignId('circuit_id')->constrained('wf_circuits')->restrictOnDelete();
            $table->unsignedInteger('circuit_version');
            $table->string('action', 60);
            $table->string('filtre', 60)->nullable();
            $table->nullableMorphs('objet');
            $table->string('reference')->nullable();
            $table->text('resume')->nullable();
            $table->decimal('montant', 15, 2)->nullable();
            $table->jsonb('donnees')->nullable();                      // paramètres de l'action, appliqués après approbation
            $table->text('motif')->nullable();
            $table->string('statut', 20)->default('EN_ATTENTE');       // EN_ATTENTE, REJETEE, ANNULEE, EXECUTEE, ECHEC
            $table->unsignedSmallInteger('etape_courante')->nullable();
            $table->timestamp('echeance_etape_at')->nullable();
            $table->foreignId('demandeur_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('demande_at');
            $table->timestamp('decide_at')->nullable();
            $table->timestamp('execute_at')->nullable();
            $table->text('erreur')->nullable();
            $table->string('cle_unicite', 160)->nullable();            // une seule demande en attente par action et objet
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
            $table->unique(['cle_unicite']);
            $table->index(['agence_id', 'statut', 'action']);
        });

        Schema::create('wf_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('wf_demandes')->cascadeOnDelete();
            $table->foreignId('etape_id')->nullable()->constrained('wf_etapes')->nullOnDelete();
            $table->unsignedSmallInteger('ordre');
            $table->string('etape_libelle');
            $table->string('decision', 20);                            // APPROUVE, REJETE
            $table->text('commentaire')->nullable();
            $table->foreignId('decide_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('decide_at');

            $table->unique(['demande_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_decisions');
        Schema::dropIfExists('wf_demandes');
        Schema::dropIfExists('wf_etapes');
        Schema::dropIfExists('wf_circuits');
    }
};
