<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paramètres système (sous-menu "Paramètres systèmes")
        Schema::create('logo', function (Blueprint $table) {
            $table->bigIncrements('id_logo');
            $table->string('titre_logo')->nullable();
            $table->text('logo_logo')->nullable();     // fichier dans public/frontend/logo
            $table->boolean('flag_logo')->nullable()->default(false);
            $table->unsignedBigInteger('id_user');
            $table->text('mot_cle')->nullable();       // valeur du paramètre
            $table->string('valeur')->nullable();      // type : LOGO, COULEUR MENU HAUT...
            $table->timestamps();
        });

        // Historique des mots de passe (empêche la réutilisation)
        Schema::create('historique_mot_de_passe', function (Blueprint $table) {
            $table->increments('id_historique');
            $table->unsignedBigInteger('id_utilisateur')->nullable();
            $table->string('ancien_mot_de_passe_hash')->nullable();
            $table->timestamp('date_changement')->nullable()->useCurrent();
            $table->timestamps();

            $table->foreign('id_utilisateur')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_mot_de_passe');
        Schema::dropIfExists('logo');
    }
};
