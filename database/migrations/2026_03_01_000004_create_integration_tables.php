<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canaux d'envoi configurables à l'écran (repris du SIH), par agence :
 *  - canaux          : e-mail SMTP, SMS, WhatsApp — agence_id NULL = configuration de la plateforme, utilisée
 *                      par les agences qui n'ont pas la leur ; secrets chiffrés (APP_KEY), mode sandbox, dernier test
 *  - modeles_messages: texte des notifications par événement (reçu, quittance, rappel d'échéance…) et canaux utilisés
 *  - envois          : journal de chaque envoi (destinataire masqué, statut, erreur, durée)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->nullable()->constrained('agences')->cascadeOnDelete();
            $table->string('code', 30);                       // EMAIL, SMS, WHATSAPP
            $table->string('fournisseur', 30)->nullable();    // SMTP, LOG, ORANGE_CI, TWILIO, INFOBIP, HTTP, SYMTEL, META
            $table->boolean('actif')->default(false);
            $table->boolean('sandbox')->default(false);
            $table->jsonb('parametres')->nullable();
            $table->text('secrets')->nullable();              // JSON chiffré (Crypt::encryptString)
            $table->timestamp('dernier_test_at')->nullable();
            $table->string('dernier_test_statut', 20)->nullable();
            $table->text('dernier_test_message')->nullable();
            $table->timestamps();
        });
        // unicité où « sans agence » (plateforme) compte comme une valeur : index sur COALESCE,
        // compatible avec toutes les versions de PostgreSQL (NULLS NOT DISTINCT exige PostgreSQL 15+)
        DB::statement('create unique index canaux_agence_id_code_unique on canaux (coalesce(agence_id, 0), code)');

        Schema::create('modeles_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('libelle');
            $table->jsonb('canaux');                          // ["EMAIL","SMS","WHATSAPP"]
            $table->string('sujet')->nullable();
            $table->text('corps_email')->nullable();
            $table->string('corps_sms', 640)->nullable();     // SMS et WhatsApp
            $table->string('variables')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['agence_id', 'code']);
        });

        Schema::create('envois', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->nullable()->index()->constrained('agences')->nullOnDelete();
            $table->string('canal', 30);
            $table->string('fournisseur', 30)->nullable();
            $table->string('destinataire');                   // masqué partiellement
            $table->string('sujet')->nullable();
            $table->string('modele', 40)->nullable();
            $table->string('statut', 20);                     // ENVOYE, ECHEC, SIMULE
            $table->text('erreur')->nullable();
            $table->string('reference_externe')->nullable();
            $table->unsignedInteger('duree_ms')->nullable();
            $table->nullableMorphs('objet');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('envoye_at');

            $table->index(['agence_id', 'canal', 'envoye_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envois');
        Schema::dropIfExists('modeles_messages');
        Schema::dropIfExists('canaux');
    }
};
