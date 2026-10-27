<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - qr_jetons / qr_verifications : signature QR de chaque document (jeton opaque, aucune donnée personnelle dans le QR),
 *   vérifiable publiquement, révocable ; journal des scans (repris du SIH)
 * - documents : GED — tout fichier (PDF, image, Excel, Word…) téléversé ou généré, rattaché à un objet métier,
 *   empreinte SHA-256, versions
 * - baux : cautions de compteurs (SODECI / CIE) et compteur CIE pris à crédit
 * - etats_lieux : index des compteurs et réparations chiffrées (à la charge du locataire ou du propriétaire)
 * - decomptes_sortie / decompte_lignes : liquidation des garanties à la sortie du locataire
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_jetons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->nullable()->constrained('agences')->cascadeOnDelete();
            $table->string('jeton', 64)->unique();
            $table->string('type_document', 40);
            $table->nullableMorphs('objet');
            $table->string('reference')->nullable();
            $table->string('libelle')->nullable();
            $table->decimal('montant', 15, 2)->nullable();
            $table->string('empreinte_sha256', 64)->nullable();     // contenu archivé ou données exportées
            $table->unsignedBigInteger('document_id')->nullable();   // fichier archivé dans la GED
            $table->timestamp('emis_at');
            $table->timestamp('revoque_at')->nullable();
            $table->string('motif_revocation')->nullable();
            $table->unsignedInteger('nb_verifications')->default(0);
            $table->timestamp('derniere_verification_at')->nullable();
            $table->timestamps();

            $table->index(['objet_type', 'objet_id', 'type_document']);
        });

        Schema::create('qr_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_jeton_id')->nullable()->constrained('qr_jetons')->nullOnDelete();
            $table->string('jeton_presente', 64);
            $table->string('resultat', 20);                          // VALIDE, REVOQUE, INCONNU, CONFORME, NON_CONFORME
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('verifie_at');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 30);
            $table->string('titre');
            $table->string('categorie', 40)->default('AUTRE');
            $table->text('description')->nullable();
            $table->nullableMorphs('documentable');
            $table->foreignId('tiers_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->string('source', 12)->default('televerse');     // televerse | genere
            $table->string('disque', 20)->default('local');
            $table->string('chemin');
            $table->string('nom_original');
            $table->string('extension', 15)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('taille');
            $table->string('empreinte_sha256', 64);
            $table->foreignId('qr_jeton_id')->nullable()->constrained('qr_jetons')->nullOnDelete();
            $table->string('etat_source', 64)->nullable();         // état de l'objet métier à la génération (réimpression à l'identique)
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('document_parent_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->boolean('confidentiel')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agence_id', 'numero']);
            $table->index(['agence_id', 'categorie']);
        });

        Schema::table('baux', function (Blueprint $table) {
            $table->decimal('caution_sodeci', 15, 2)->default(0)->after('depot_garantie');
            $table->decimal('caution_cie', 15, 2)->default(0)->after('caution_sodeci');
            $table->string('compteur_sodeci_numero', 40)->nullable()->after('caution_cie');
            $table->string('compteur_cie_numero', 40)->nullable()->after('compteur_sodeci_numero');
            $table->boolean('compteur_cie_credit')->default(false)->after('compteur_cie_numero');   // compteur prépayé pris à crédit
            $table->decimal('compteur_cie_montant_credit', 15, 2)->default(0)->after('compteur_cie_credit');
        });

        Schema::table('etats_lieux', function (Blueprint $table) {
            $table->json('compteurs')->nullable();                   // {cie_index, sodeci_index}
            $table->json('reparations')->nullable();                 // [{libelle, montant, a_charge: locataire|proprietaire}]
        });

        Schema::create('decomptes_sortie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 30)->nullable();
            $table->foreignId('bail_id')->constrained('baux')->cascadeOnDelete();
            $table->foreignId('tiers_id')->constrained('tiers');
            $table->foreignId('etat_lieux_id')->nullable()->constrained('etats_lieux')->nullOnDelete();
            $table->date('date_sortie');
            $table->string('statut', 20)->default('brouillon');     // brouillon | valide | rembourse | solde | reste_du
            $table->decimal('total_credits', 15, 2)->default(0);     // garanties + crédits dus au locataire
            $table->decimal('total_retenues', 15, 2)->default(0);    // impayés + retenues
            $table->decimal('montant_compense', 15, 2)->default(0);
            $table->decimal('montant_a_rembourser', 15, 2)->default(0);
            $table->decimal('reste_du', 15, 2)->default(0);
            $table->foreignId('encaissement_id')->nullable()->constrained('encaissements')->nullOnDelete();   // compensation
            $table->foreignId('depense_id')->nullable()->constrained('depenses')->nullOnDelete();             // remboursement
            $table->foreignId('depense_compteur_id')->nullable()->constrained('depenses')->nullOnDelete();    // crédit compteur CIE
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('rembourse_le')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
        });

        Schema::create('decompte_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('decompte_id')->constrained('decomptes_sortie')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('sens', 6);                               // credit (dû au locataire) | debit (dû par le locataire)
            $table->string('libelle');
            $table->decimal('montant', 15, 2);
            $table->foreignId('loyer_id')->nullable()->constrained('loyers')->nullOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decompte_lignes');
        Schema::dropIfExists('decomptes_sortie');
        Schema::table('etats_lieux', fn (Blueprint $table) => $table->dropColumn(['compteurs', 'reparations']));
        Schema::table('baux', fn (Blueprint $table) => $table->dropColumn(['caution_sodeci', 'caution_cie', 'compteur_sodeci_numero', 'compteur_cie_numero', 'compteur_cie_credit', 'compteur_cie_montant_credit']));
        Schema::dropIfExists('documents');
        Schema::dropIfExists('qr_verifications');
        Schema::dropIfExists('qr_jetons');
    }
};
