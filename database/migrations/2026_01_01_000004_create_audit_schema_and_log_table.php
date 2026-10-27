<?php

use App\Helpers\Audit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit dans un schéma séparé (PostgreSQL) : audit.log.
 * Sur un autre SGBD (sans schémas), la table s'appelle audit_log.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return Audit::connection();
    }

    public function up(): void
    {
        if (Audit::supportsSchemas()) {
            DB::connection($this->getConnection())
                ->statement('CREATE SCHEMA IF NOT EXISTS "' . config('rbac.audit.schema') . '"');
        }

        // le schéma audit n'est pas vidé par migrate:fresh : on conserve l'historique existant
        if (Schema::connection($this->getConnection())->hasTable(Audit::table())) {
            return;
        }

        Schema::connection($this->getConnection())->create(Audit::table(), function (Blueprint $table) {
            $table->bigIncrements('id_log');
            $table->string('nom_hote_log', 150)->nullable();
            $table->string('ip_addr_log', 50)->nullable();
            $table->text('agent_utilisateur_log')->nullable();
            $table->string('niveau_log')->nullable();
            $table->text('action_log')->nullable();
            $table->string('identifiant_log')->nullable();
            $table->string('utilisateur_log')->nullable();
            $table->string('role_log')->nullable();
            $table->string('etat_log')->nullable();
            $table->string('menu_log')->nullable();
            $table->string('code_piece_log')->nullable();
            $table->string('objet_log')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(Audit::table());
    }
};
