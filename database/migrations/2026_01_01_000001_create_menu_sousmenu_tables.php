<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modules du menu
        Schema::create('menu', function (Blueprint $table) {
            $table->increments('id_menu');
            $table->string('menu');
            $table->integer('priorite_menu')->nullable();
            $table->boolean('is_valide')->nullable()->default(true);
            $table->string('icone')->nullable();
            $table->boolean('flag_is_admin')->nullable()->default(false); // visible seulement par un SPADMIN
            $table->timestamps();
        });

        // Sous-modules : "sousmenu" = url / nom de route
        Schema::create('sousmenu', function (Blueprint $table) {
            $table->bigIncrements('id_sousmenu');
            $table->unsignedInteger('menu_id_menu');
            $table->string('sousmenu')->nullable();
            $table->string('libelle')->nullable();
            $table->integer('priorite_sousmenu')->nullable();
            $table->boolean('is_valide')->nullable()->default(true);
            $table->timestamps();

            $table->foreign('menu_id_menu')->references('id_menu')->on('menu')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sousmenu');
        Schema::dropIfExists('menu');
    }
};
