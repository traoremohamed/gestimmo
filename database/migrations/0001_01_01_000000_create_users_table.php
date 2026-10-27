<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace la migration users par défaut de Laravel (même nom de fichier).
 * Colonnes reprises de la table users de CAPAVENIRJEUNESCI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prenom_users')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('login_users')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('genre_users', 10)->nullable();
            $table->string('cel_users')->nullable();
            $table->string('tel_users')->nullable();
            $table->bigInteger('indicatif_cel_users')->nullable();
            $table->bigInteger('indicatif_tel_users')->nullable();
            $table->string('localisation_users')->nullable();
            $table->string('adresse_users')->nullable();
            $table->text('photo_profil')->nullable();
            $table->bigInteger('id_partenaire')->nullable();
            $table->unsignedBigInteger('num_agce')->nullable();
            $table->string('email_agce')->nullable();
            $table->unsignedBigInteger('id_direction')->nullable();
            $table->unsignedBigInteger('id_departement')->nullable();
            $table->unsignedBigInteger('id_service')->nullable();
            $table->boolean('flag_mdp')->nullable()->default(false);
            $table->boolean('flag_mod_pswd_first')->nullable()->default(false);
            $table->boolean('flag_actif_users')->nullable()->default(true);
            $table->boolean('flag_demission_users')->nullable()->default(false);
            $table->boolean('flag_admin_users')->nullable()->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
