<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finances (encaissements affectés, dépenses), interventions, séjours meublés,
 * marketing, site vitrine et objectifs commerciaux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encaissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40); // n° de reçu
            $table->foreignId('tiers_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->date('date_encaissement');
            $table->decimal('montant', 15, 2);
            $table->string('mode_paiement', 50);
            $table->string('reference')->nullable(); // n° de chèque, transaction…
            $table->string('banque')->nullable();
            $table->string('activite', 20);          // vente | location | syndic | meuble | autre
            $table->text('observation')->nullable();
            $table->string('statut', 20)->default('valide'); // valide | annule
            $table->text('motif_annulation')->nullable();
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annule_le')->nullable();
            $table->foreignId('caissier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
            $table->index(['agence_id', 'date_encaissement']);
        });

        // un encaissement peut solder plusieurs dettes : échéance, frais, loyer, appel de fonds, séjour
        Schema::create('encaissement_affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encaissement_id')->constrained('encaissements')->cascadeOnDelete();
            $table->morphs('affectable');
            $table->decimal('montant', 15, 2);
            $table->timestamps();
        });

        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->date('date_depense');
            $table->string('categorie', 50);
            $table->string('libelle');
            $table->decimal('montant', 15, 2);
            $table->foreignId('fournisseur_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->foreignId('beneficiaire_id')->nullable()->constrained('tiers')->nullOnDelete(); // remboursement client
            $table->foreignId('copropriete_id')->nullable()->constrained('coproprietes')->nullOnDelete();
            $table->foreignId('bien_id')->nullable()->constrained('biens')->nullOnDelete();
            $table->boolean('refacturable_proprietaire')->default(false);
            $table->foreignId('reversement_id')->nullable()->constrained('reversements')->nullOnDelete();
            $table->string('mode_paiement', 50)->nullable();
            $table->string('reference')->nullable();
            $table->string('justificatif')->nullable();
            $table->string('statut', 20)->default('payee'); // a_payer | payee | annulee
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->foreign('depense_remboursement_id')->references('id')->on('depenses')->nullOnDelete();
        });

        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->foreignId('bien_id')->nullable()->constrained('biens')->nullOnDelete();
            $table->foreignId('copropriete_id')->nullable()->constrained('coproprietes')->nullOnDelete();
            $table->foreignId('reclamation_id')->nullable()->constrained('reclamations')->nullOnDelete();
            $table->foreignId('fournisseur_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->string('objet');
            $table->text('description')->nullable();
            $table->string('priorite', 10)->default('normale');
            $table->string('statut', 20)->default('signalee'); // signalee | planifiee | en_cours | terminee | annulee
            $table->date('date_signalement');
            $table->date('date_prevue')->nullable();
            $table->date('date_realisation')->nullable();
            $table->decimal('cout_estime', 15, 2)->nullable();
            $table->decimal('cout_reel', 15, 2)->nullable();
            $table->string('a_charge_de', 20)->default('proprietaire'); // proprietaire | locataire | copropriete | agence
            $table->foreignId('depense_id')->nullable()->constrained('depenses')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
        });

        Schema::create('sejours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->foreignId('bien_id')->constrained('biens')->restrictOnDelete();
            $table->foreignId('tiers_id')->constrained('tiers')->restrictOnDelete();
            $table->date('date_arrivee');
            $table->date('date_depart');
            $table->unsignedSmallInteger('nb_nuits');
            $table->unsignedSmallInteger('nb_personnes')->default(1);
            $table->decimal('prix_nuitee', 15, 2);
            $table->decimal('remise', 15, 2)->default(0);
            $table->decimal('frais_supplementaires', 15, 2)->default(0);
            $table->decimal('montant', 15, 2);
            $table->decimal('montant_paye', 15, 2)->default(0);
            $table->string('statut', 20)->default('reserve'); // reserve | confirme | en_cours | termine | annule
            $table->string('source', 30)->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
            $table->index(['bien_id', 'date_arrivee', 'date_depart']);
        });

        Schema::create('campagnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('nom');
            $table->string('canal', 10)->default('email'); // email | sms
            $table->string('objet')->nullable();
            $table->text('message');
            $table->json('cible')->nullable();               // filtres : rôles, type de bien, programme…
            $table->string('statut', 20)->default('brouillon'); // brouillon | envoyee
            $table->timestamp('envoyee_le')->nullable();
            $table->unsignedInteger('nb_destinataires')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campagne_destinataires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campagne_id')->constrained('campagnes')->cascadeOnDelete();
            $table->foreignId('tiers_id')->nullable()->constrained('tiers')->nullOnDelete();
            $table->string('nom')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('statut', 20)->default('en_attente'); // en_attente | envoye | echec
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('type', 20)->default('actualite'); // actualite | article | page
            $table->string('categorie', 50)->nullable();
            $table->string('titre');
            $table->string('slug');
            $table->string('resume', 500)->nullable();
            $table->text('contenu')->nullable();
            $table->string('image')->nullable();
            $table->string('video_url')->nullable();
            $table->boolean('publie')->default(false);
            $table->timestamp('publie_le')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'slug']);
        });

        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('titre');
            $table->string('sous_titre')->nullable();
            $table->string('image')->nullable();
            $table->string('video_url')->nullable();
            $table->string('bouton_libelle', 60)->nullable();
            $table->string('bouton_lien')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('temoignages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('auteur');
            $table->string('fonction')->nullable();
            $table->text('contenu');
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('note')->default(5);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('objectifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('activite', 20)->default('vente'); // vente | location
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->decimal('montant_objectif', 15, 2);
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('ventes', fn (Blueprint $table) => $table->dropForeign(['depense_remboursement_id']));

        foreach (['objectifs', 'temoignages', 'slides', 'publications', 'campagne_destinataires', 'campagnes', 'sejours',
            'interventions', 'depenses', 'encaissement_affectations', 'encaissements'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
