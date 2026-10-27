<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ressources humaines, gestion du personnel et paie (modèle repris de GEST COMM, adapté aux agences) :
 *  rh_employes, rh_contrats, rh_historique (carrière), rh_presences (pointage), rh_absences (congés avec validation),
 *  rh_avances (avances / prêts), rh_rubriques (rubriques de paie : CNPS, CMU, ITS…), rh_periodes_paie,
 *  rh_variables_paie (primes, heures sup), rh_bulletins, rh_paiements_salaire.
 * Départements, postes et types d'absence sont des listes de valeurs de l'agence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_employes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete(); // compte applicatif (commissions, agenda)
            $table->string('matricule', 30);
            $table->string('civilite', 10)->nullable();
            $table->string('nom');
            $table->string('prenoms')->nullable();
            $table->char('sexe', 1)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->foreignId('nationalite_id')->nullable()->constrained('pays')->nullOnDelete();
            $table->string('piece_identite', 60)->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->string('departement', 50)->nullable();         // referentiel departement
            $table->string('poste', 50)->nullable();               // referentiel poste
            $table->string('situation_familiale', 20)->nullable(); // CELIBATAIRE | MARIE | DIVORCE | VEUF
            $table->unsignedSmallInteger('nombre_enfants')->default(0);
            $table->decimal('nombre_parts', 4, 1)->default(1);     // parts fiscales (ITS)
            $table->string('numero_cnps', 40)->nullable();
            $table->string('contact_urgence')->nullable();
            $table->string('banque')->nullable();
            $table->string('rib', 60)->nullable();
            $table->date('date_embauche');
            $table->date('date_sortie')->nullable();
            $table->string('motif_sortie')->nullable();
            $table->decimal('solde_conges', 6, 2)->default(0);
            $table->string('statut', 12)->default('actif');        // actif | suspendu | sorti
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agence_id', 'matricule']);
        });

        Schema::create('rh_contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('employe_id')->constrained('rh_employes')->cascadeOnDelete();
            $table->string('type_contrat', 15)->default('CDI');    // CDI | CDD | STAGE | JOURNALIER | PRESTATAIRE
            $table->string('categorie', 20)->nullable();            // catégorie de la convention collective
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('mode_remuneration', 15)->default('FIXE'); // FIXE | JOURNALIER | COMMISSION
            $table->decimal('salaire_base', 15, 2)->default(0);     // mensuel
            $table->decimal('sursalaire', 15, 2)->default(0);
            $table->decimal('taux_journalier', 15, 2)->default(0);
            $table->decimal('taux_commission', 6, 3)->default(0);   // % du CA des ventes validées du commercial
            $table->decimal('heures_hebdo', 5, 2)->default(40);
            $table->decimal('jours_par_mois', 5, 2)->default(26);
            $table->boolean('soumis_cotisations')->default(true);
            $table->boolean('soumis_impot')->default(true);
            $table->json('rubriques_fixes')->nullable();            // {LOGEMENT: 50000, TRANSPORT: 30000}
            $table->string('mode_paiement', 50)->default('VIREMENT');
            $table->string('statut', 10)->default('actif');         // actif | clos
            $table->timestamps();

            $table->index(['employe_id', 'statut']);
        });

        Schema::create('rh_historique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('rh_employes')->cascadeOnDelete();
            $table->string('type', 20);                             // embauche | promotion | mutation | augmentation | sanction | formation | evaluation | sortie
            $table->date('date_effet');
            $table->string('libelle');
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rh_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('employe_id')->constrained('rh_employes')->cascadeOnDelete();
            $table->date('date');
            $table->string('statut', 15)->default('present');       // present | absent | retard | demi_journee | conge | repos
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->decimal('heures_supplementaires', 5, 2)->default(0);
            $table->string('commentaire')->nullable();
            $table->timestamps();

            $table->unique(['employe_id', 'date']);
        });

        Schema::create('rh_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('employe_id')->constrained('rh_employes')->cascadeOnDelete();
            $table->string('type', 50);                             // referentiel type_absence (meta : remuneree, decompte_conges)
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('nombre_jours', 6, 2);
            $table->text('motif')->nullable();
            $table->string('statut', 12)->default('demandee');      // demandee | validee | refusee | annulee
            $table->foreignId('decide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->string('commentaire_decision')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rh_avances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('employe_id')->constrained('rh_employes')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->string('type', 10)->default('avance');          // avance | pret
            $table->date('date_octroi');
            $table->decimal('montant', 15, 2);
            $table->unsignedSmallInteger('nombre_echeances')->default(1);
            $table->decimal('montant_echeance', 15, 2);
            $table->decimal('montant_rembourse', 15, 2)->default(0);
            $table->string('mode_paiement', 50)->default('ESPECES');
            $table->string('statut', 12)->default('en_cours');      // en_cours | soldee | annulee
            $table->text('motif')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
        });

        Schema::create('rh_rubriques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('numero', 10)->nullable();
            $table->string('libelle');
            $table->string('type', 25);                             // GAIN | RETENUE | COTISATION_SALARIALE | COTISATION_PATRONALE | IMPOT
            $table->string('mode_calcul', 15);                      // SALAIRE_BASE | JOURS | COMMISSION | FIXE | SAISIE | ANCIENNETE | POURCENTAGE | BAREME | AVANCE
            $table->string('base', 20)->nullable();                 // BASE | BRUT | BRUT_IMPOSABLE | BRUT_COTISABLE | CA_VENDEUR
            $table->decimal('taux', 8, 4)->nullable();
            $table->decimal('montant', 15, 2)->nullable();
            $table->decimal('plafond', 15, 2)->nullable();
            $table->decimal('franchise', 15, 2)->nullable();        // part non imposable (indemnité de transport)
            $table->json('bareme')->nullable();                     // [[de, a, taux %], …] mensuel
            $table->json('reduction_parts')->nullable();            // réduction d'impôt pour charges de famille (RICF)
            $table->unsignedSmallInteger('anciennete_min_ans')->nullable();
            $table->boolean('imposable')->default(true);
            $table->boolean('cotisable')->default(true);
            $table->boolean('proratiser')->default(false);
            $table->string('groupe', 20)->nullable();               // GAINS | NON_IMPOSABLES | RETENUES | AUTRES | PATRONAL
            $table->string('compte_charge', 20)->nullable();
            $table->string('compte_tiers', 20)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['agence_id', 'code']);
        });

        Schema::create('rh_periodes_paie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('code', 10);                             // 2026-11
            $table->string('libelle');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->date('date_paiement')->nullable();
            $table->string('statut', 12)->default('ouverte');       // ouverte | calculee | validee | payee | cloturee
            $table->decimal('total_brut', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->decimal('total_cout', 15, 2)->default(0);
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();

            $table->unique(['agence_id', 'code']);
        });

        Schema::create('rh_variables_paie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('rh_employes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('rh_periodes_paie')->cascadeOnDelete();
            $table->string('rubrique_code', 20);
            $table->decimal('quantite', 10, 2)->nullable();
            $table->decimal('montant', 15, 2);
            $table->string('libelle')->nullable();
            $table->timestamps();
        });

        Schema::create('rh_bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->foreignId('periode_id')->constrained('rh_periodes_paie')->cascadeOnDelete();
            $table->foreignId('employe_id')->constrained('rh_employes')->restrictOnDelete();
            $table->foreignId('contrat_id')->constrained('rh_contrats')->restrictOnDelete();
            $table->decimal('jours_travailles', 6, 2)->default(0);
            $table->json('lignes');
            $table->json('totaux')->nullable();
            $table->decimal('brut', 15, 2);
            $table->decimal('brut_imposable', 15, 2);
            $table->decimal('cotisations_salariales', 15, 2);
            $table->decimal('cotisations_patronales', 15, 2);
            $table->decimal('impots', 15, 2);
            $table->decimal('retenues', 15, 2);
            $table->decimal('net_a_payer', 15, 2);
            $table->decimal('montant_paye', 15, 2)->default(0);
            $table->decimal('cout_employeur', 15, 2);
            $table->string('statut', 10)->default('calcule');       // calcule | valide | partiel | paye | annule
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
            $table->unique(['employe_id', 'periode_id']);
        });

        Schema::create('rh_paiements_salaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('numero', 40);
            $table->foreignId('bulletin_id')->constrained('rh_bulletins')->restrictOnDelete();
            $table->foreignId('employe_id')->constrained('rh_employes')->restrictOnDelete();
            $table->date('date_paiement');
            $table->decimal('montant', 15, 2);
            $table->string('mode_paiement', 50);
            $table->string('reference')->nullable();
            $table->string('statut', 10)->default('valide');        // valide | annule
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agence_id', 'numero']);
        });
    }

    public function down(): void
    {
        foreach (['rh_paiements_salaire', 'rh_bulletins', 'rh_variables_paie', 'rh_periodes_paie', 'rh_rubriques', 'rh_avances',
            'rh_absences', 'rh_presences', 'rh_historique', 'rh_contrats', 'rh_employes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
