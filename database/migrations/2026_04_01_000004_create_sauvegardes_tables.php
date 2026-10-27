<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sauvegardes de la plateforme (reprises de MARIEATTIEKESAAS) : base + code + documents de toutes les agences,
 * archive chiffrée (AES-256) envoyée vers une ou plusieurs destinations (dossier local / réseau, SFTP, WebDAV, Google Drive).
 * Tables de la plateforme : pas d'agence_id, seul le super administrateur y accède.
 *  sys_sauvegarde_config        : planification, contenu, rétention, clé de chiffrement (chiffrée)
 *  sys_sauvegarde_destinations  : destinations et leurs paramètres (secrets chiffrés)
 *  sys_sauvegardes              : historique (fichier, taille, empreinte, résultat par destination)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_sauvegarde_config', function (Blueprint $table) {
            $table->id();
            $table->boolean('actif')->default(false);                   // sauvegarde planifiée
            $table->string('frequence', 20)->default('QUOTIDIENNE');    // QUOTIDIENNE, HEBDOMADAIRE
            $table->unsignedTinyInteger('jour_semaine')->default(7);    // 1 = lundi … 7 = dimanche (hebdomadaire)
            $table->string('heure', 5)->default('02:00');
            $table->boolean('inclure_base')->default(true);
            $table->boolean('inclure_code')->default(true);
            $table->boolean('inclure_documents')->default(true);
            $table->boolean('inclure_vendor')->default(false);
            $table->unsignedSmallInteger('retention_locale')->default(7);  // archives gardées sur le serveur
            $table->text('cle_chiffrement')->nullable();                // chiffrée par APP_KEY, jamais réaffichée
            $table->string('pg_dump_chemin')->nullable();               // vide = « pg_dump » du PATH
            $table->timestamps();
        });

        Schema::create('sys_sauvegarde_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('type', 20);                                 // LOCAL, SFTP, WEBDAV, GOOGLE_DRIVE
            $table->jsonb('parametres')->nullable();                    // valeurs non sensibles
            $table->text('secrets')->nullable();                        // JSON chiffré (mots de passe, clés, jetons)
            $table->unsignedSmallInteger('retention')->default(14);     // archives gardées sur la destination
            $table->boolean('actif')->default(true);
            $table->timestamp('dernier_test_at')->nullable();
            $table->string('dernier_test_resultat', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('sys_sauvegardes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 40)->unique();
            $table->string('declenchement', 20)->default('MANUEL');     // MANUEL, PLANIFIEE
            $table->jsonb('contenu')->nullable();                       // ["BASE","CODE","DOCUMENTS"]
            $table->string('statut', 20)->default('EN_COURS');          // EN_COURS, SUCCES, PARTIEL, ECHEC
            $table->string('fichier')->nullable();
            $table->string('chemin_local')->nullable();
            $table->unsignedBigInteger('taille_octets')->default(0);
            $table->char('empreinte_sha256', 64)->nullable();
            $table->boolean('chiffree')->default(false);
            $table->jsonb('destinations')->nullable();                  // [{id, libelle, statut, message, duree_ms}]
            $table->jsonb('manifeste')->nullable();
            $table->text('erreur')->nullable();
            $table->timestamp('debut_at');
            $table->timestamp('fin_at')->nullable();
            $table->foreignId('lance_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_sauvegardes');
        Schema::dropIfExists('sys_sauvegarde_destinations');
        Schema::dropIfExists('sys_sauvegarde_config');
    }
};
