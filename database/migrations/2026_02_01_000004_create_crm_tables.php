<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('tiers_id')->constrained('tiers')->cascadeOnDelete();
            $table->foreignId('commercial_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('projet', 20)->default('achat'); // achat | location | gestion | syndic | meuble
            $table->string('type_bien', 50)->nullable();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->decimal('budget_min', 15, 2)->nullable();
            $table->decimal('budget_max', 15, 2)->nullable();
            $table->decimal('superficie_min', 14, 2)->nullable();
            $table->string('localisation')->nullable();
            $table->string('canal', 50)->nullable();
            $table->string('statut', 20)->default('nouveau');
            $table->date('date_relance')->nullable();
            $table->text('commentaire')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->index(['agence_id', 'statut']);
        });

        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('tiers_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->nullOnDelete();
            $table->foreignId('bien_id')->nullable()->constrained('biens')->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // agent
            $table->string('objet', 50)->default('VISITE');
            $table->dateTime('debut');
            $table->dateTime('fin')->nullable();
            $table->string('lieu')->nullable();
            $table->string('statut', 20)->default('planifie');
            $table->dateTime('reporte_au')->nullable();
            $table->text('commentaire')->nullable();
            $table->text('compte_rendu')->nullable();
            $table->string('source', 20)->default('backoffice'); // backoffice | vitrine
            $table->string('contact_nom')->nullable();            // demandes publiques sans tiers
            $table->string('contact_telephone', 50)->nullable();
            $table->string('contact_email')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->index(['agence_id', 'debut']);
        });

        Schema::create('reclamations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('tiers_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->foreignId('bien_id')->nullable()->constrained('biens')->nullOnDelete();
            $table->string('type', 50)->nullable();
            $table->string('priorite', 10)->default('normale'); // basse | normale | haute | urgente
            $table->string('objet');
            $table->text('message');
            $table->string('statut', 20)->default('ouverte');
            $table->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolue_le')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('demandes_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('bien_id')->nullable()->constrained('biens')->nullOnDelete();
            $table->string('nom');
            $table->string('email')->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('sujet')->nullable();
            $table->text('message');
            $table->boolean('newsletter')->default(false);
            $table->string('statut', 20)->default('nouvelle'); // nouvelle | traitee | archivee
            $table->text('reponse')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['demandes_contact', 'reclamations', 'rendez_vous', 'prospects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
