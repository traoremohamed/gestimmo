<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

/**
 * Journalisation des actions utilisateurs dans le schéma d'audit (audit.log).
 *
 * Audit::logSave([
 *     'action' => 'CREER',
 *     'code_piece' => $user->id,
 *     'menu' => 'LISTE DES UTILISATEURS',
 *     'etat' => 'Succès',
 *     'objet' => 'ADMINISTRATION',
 * ]);
 */
class Audit
{
    public static function logSave(array $data): void
    {
        if (!config('rbac.audit.enabled', true)) {
            return;
        }

        $context = array_filter([
            'code_piece' => $data['code_piece'] ?? null,
            'menu' => $data['menu'] ?? null,
            'etat' => $data['etat'] ?? null,
            'objet' => $data['objet'] ?? null,
            'objet_type' => $data['objet_type'] ?? null,
            'objet_id' => $data['objet_id'] ?? null,
            'anciennes_valeurs' => $data['anciennes_valeurs'] ?? null,
            'nouvelles_valeurs' => $data['nouvelles_valeurs'] ?? null,
            'motif' => $data['motif'] ?? null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        try {
            Log::channel('audit')->info($data['action'] ?? '', $context);
        } catch (\Throwable $e) {
            // l'audit ne doit jamais bloquer l'action métier
            Log::error('Audit impossible : ' . $e->getMessage());
        }
    }

    /**
     * Nom qualifié de la table d'audit (ex: audit.log).
     */
    public static function table(): string
    {
        $schema = config('rbac.audit.schema');
        $table = config('rbac.audit.table', 'log');

        return static::supportsSchemas() && $schema ? $schema . '.' . $table : ($schema ? $schema . '_' : '') . $table;
    }

    public static function connection(): ?string
    {
        return config('rbac.audit.connection');
    }

    public static function supportsSchemas(): bool
    {
        $connection = static::connection() ?? config('database.default');

        return config("database.connections.$connection.driver") === 'pgsql';
    }
}
