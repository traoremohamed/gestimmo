<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recouvrement des impayés (ventes, loyers, charges de copropriété, séjours) :
 *  regles_relance : niveaux de relance paramétrables (J+n après l'échéance, canal, modèle de message)
 *  recouvrements  : dossier par débiteur et activité (montant dû, niveau atteint, statut, gestionnaire)
 *  relances       : chaque relance envoyée ou éditée (e-mail, SMS, courrier, appel, visite)
 *  promesses_paiement : engagement du débiteur (date, montant), suivi tenu / non tenu
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regles_relance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->unsignedTinyInteger('niveau');
            $table->string('libelle');
            $table->unsignedSmallInteger('delai_jours');          // jours de retard déclenchant le niveau
            $table->string('canal', 20)->default('email');        // email | sms | courrier | appel
            $table->string('activites')->nullable();              // vente,location,syndic,meuble (vide = toutes)
            $table->string('objet')->nullable();
            $table->text('message');                              // variables : {nom} {montant} {retard} {agence} {details}
            $table->boolean('automatique')->default(true);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['agence_id', 'niveau']);
        });

        Schema::create('recouvrements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('tiers_id')->constrained('tiers')->cascadeOnDelete();
            $table->string('activite', 20);                       // vente | location | syndic | meuble
            $table->decimal('montant_du', 15, 2)->default(0);     // reste dû échu, mis à jour à chaque analyse
            $table->unsignedSmallInteger('nombre_impayes')->default(0);
            $table->date('plus_ancienne_echeance')->nullable();
            $table->unsignedTinyInteger('niveau')->default(0);    // dernier niveau de relance atteint
            $table->string('statut', 20)->default('amiable');     // amiable | promesse | contentieux | protocole | solde | abandonne
            $table->foreignId('gestionnaire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('prochaine_action')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamp('solde_le')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'tiers_id', 'activite']);
            $table->index(['agence_id', 'statut']);
        });

        Schema::create('relances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('recouvrement_id')->constrained('recouvrements')->cascadeOnDelete();
            $table->unsignedTinyInteger('niveau')->default(0);
            $table->string('canal', 20);
            $table->decimal('montant_du', 15, 2);
            $table->string('objet')->nullable();
            $table->text('message')->nullable();
            $table->string('statut', 20)->default('envoyee');     // envoyee | echec | a_imprimer | realisee
            $table->boolean('automatique')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('promesses_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('recouvrement_id')->constrained('recouvrements')->cascadeOnDelete();
            $table->date('date_promesse');
            $table->decimal('montant', 15, 2);
            $table->string('statut', 20)->default('en_attente');  // en_attente | tenue | non_tenue | annulee
            $table->text('commentaire')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['promesses_paiement', 'relances', 'recouvrements', 'regles_relance'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
