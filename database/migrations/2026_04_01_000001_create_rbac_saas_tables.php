<?php

use App\Helpers\Audit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC multi-agences (repris de MARIEATTIEKESAAS et du SIH) :
 *
 *  users            : super administrateur de la plateforme, période d'accès, verrouillage après échecs, dernière connexion
 *  user_agences     : agences supplémentaires accessibles à un utilisateur (groupe possédant plusieurs agences)
 *  roles.agence_id  : profil propre à une agence (vide = profil modèle de la plateforme, proposé à toutes)
 *  menu / sousmenu  : code stable, description ; sous-menus imbriqués (parent_id), groupes, icône, réservé plateforme
 *  journal d'audit  : agence, utilisateur, objet, valeurs avant / après, requête ; protégé contre toute modification
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('est_super_admin')->default(false)->after('agence_id');
            $table->string('fonction')->nullable();
            $table->date('date_debut_acces')->nullable();
            $table->date('date_fin_acces')->nullable();
            $table->timestamp('derniere_connexion_at')->nullable();
            $table->string('derniere_ip', 45)->nullable();
            $table->unsignedSmallInteger('tentatives_echouees')->default(0);
            $table->timestamp('verrouille_jusqu_a')->nullable();
            $table->timestamp('mot_de_passe_change_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('user_agences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'agence_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('agence_id')->nullable()->after('id')->constrained('agences')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->string('page_accueil', 80)->nullable();
        });
        // le nom d'un profil n'est unique qu'au sein d'une agence
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['agence_id', 'name', 'guard_name'])->nullsNotDistinct();
        });

        Schema::table('menu', function (Blueprint $table) {
            $table->string('code', 60)->nullable()->unique()->after('id_menu');
            $table->string('description')->nullable()->after('menu');
        });

        Schema::table('sousmenu', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('menu_id_menu');
            $table->string('code', 60)->nullable()->unique()->after('id_sousmenu');
            $table->string('icone')->nullable()->after('libelle');
            $table->string('description')->nullable()->after('icone');
            $table->boolean('flag_is_admin')->default(false)->after('is_valide');      // écran de la plateforme (super administrateur)

            $table->foreign('parent_id')->references('id_sousmenu')->on('sousmenu')->cascadeOnDelete();
            $table->index(['menu_id_menu', 'parent_id', 'priorite_sousmenu']);
        });

        // les comptes portant le profil SPADMIN deviennent super administrateurs de la plateforme
        DB::table('users')->whereIn('id', DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.codes_roles_detail', 'SPADMIN')->select('model_has_roles.model_id'))->update(['est_super_admin' => true]);

        $this->enrichirAudit();
    }

    protected function enrichirAudit(): void
    {
        $connexion = Audit::connection();
        $schema = Schema::connection($connexion);
        $table = Audit::table();
        if (!$schema->hasTable($table)) {
            return;
        }

        $colonnes = [
            'agence_id' => fn (Blueprint $t) => $t->unsignedBigInteger('agence_id')->nullable(),
            'user_id' => fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable(),
            'objet_type' => fn (Blueprint $t) => $t->string('objet_type', 100)->nullable(),
            'objet_id' => fn (Blueprint $t) => $t->string('objet_id', 50)->nullable(),
            'anciennes_valeurs' => fn (Blueprint $t) => $t->jsonb('anciennes_valeurs')->nullable(),
            'nouvelles_valeurs' => fn (Blueprint $t) => $t->jsonb('nouvelles_valeurs')->nullable(),
            'motif_log' => fn (Blueprint $t) => $t->text('motif_log')->nullable(),
            'url_log' => fn (Blueprint $t) => $t->text('url_log')->nullable(),
            'methode_log' => fn (Blueprint $t) => $t->string('methode_log', 10)->nullable(),
            'route_log' => fn (Blueprint $t) => $t->string('route_log', 150)->nullable(),
        ];
        foreach ($colonnes as $nom => $definition) {
            if (!$schema->hasColumn($table, $nom)) {
                $schema->table($table, fn (Blueprint $t) => $definition($t));
            }
        }
        $index = collect($schema->getIndexes($table))->pluck('name')->all();
        $prefixe = str_replace('.', '_', $table);
        $schema->table($table, function (Blueprint $t) use ($index, $prefixe) {
            if (!in_array($prefixe . '_agence_index', $index, true)) {
                $t->index(['agence_id', 'created_at'], $prefixe . '_agence_index');
            }
            if (!in_array($prefixe . '_objet_index', $index, true)) {
                $t->index(['objet_type', 'objet_id'], $prefixe . '_objet_index');
            }
        });

        if (Audit::supportsSchemas()) {
            $schemaAudit = config('rbac.audit.schema');
            DB::connection($connexion)->unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION "$schemaAudit".interdire_modification_audit() RETURNS trigger AS \$\$
                BEGIN
                    RAISE EXCEPTION 'Le journal d''audit est protégé : modification ou suppression interdite';
                END;
                \$\$ LANGUAGE plpgsql;
                DROP TRIGGER IF EXISTS audit_log_protection ON $table;
                CREATE TRIGGER audit_log_protection BEFORE UPDATE OR DELETE ON $table
                    FOR EACH ROW EXECUTE FUNCTION "$schemaAudit".interdire_modification_audit();
            SQL);
        }
    }

    public function down(): void
    {
        if (Audit::supportsSchemas()) {
            DB::connection(Audit::connection())->unprepared('DROP TRIGGER IF EXISTS audit_log_protection ON ' . Audit::table());
        }
        Schema::table('sousmenu', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['menu_id_menu', 'parent_id', 'priorite_sousmenu']);
            $table->dropColumn(['parent_id', 'code', 'icone', 'description', 'flag_is_admin']);
        });
        Schema::table('menu', fn (Blueprint $table) => $table->dropColumn(['code', 'description']));
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['agence_id', 'name', 'guard_name']);
            $table->dropConstrainedForeignId('agence_id');
            $table->dropColumn(['description', 'page_accueil']);
            $table->unique(['name', 'guard_name']);
        });
        Schema::dropIfExists('user_agences');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['est_super_admin', 'fonction', 'date_debut_acces', 'date_fin_acces', 'derniere_connexion_at', 'derniere_ip',
                'tentatives_echouees', 'verrouille_jusqu_a', 'mot_de_passe_change_at']);
        });
    }
};
