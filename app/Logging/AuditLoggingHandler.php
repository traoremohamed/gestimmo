<?php

namespace App\Logging;

use App\Helpers\Audit;
use App\Helpers\Menu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Écrit chaque enregistrement du canal "audit" dans la table audit.log.
 */
class AuditLoggingHandler extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Info, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $request = app()->bound('request') ? request() : null;
        $user = Auth::user();

        DB::connection(Audit::connection())->table(Audit::table())->insert([
            'nom_hote_log' => gethostname(),
            'niveau_log' => $record->level->getName(),
            'ip_addr_log' => $request?->ip(),
            'agent_utilisateur_log' => $request?->userAgent(),
            'identifiant_log' => $user?->login_users,
            'utilisateur_log' => $user ? trim($user->name . ' ' . $user->prenom_users) : null,
            'role_log' => $user ? Menu::get_menu_profil($user->id) : null,
            'action_log' => $record->message,
            'code_piece_log' => $record->context['code_piece'] ?? null,
            'menu_log' => $record->context['menu'] ?? null,
            'etat_log' => $record->context['etat'] ?? null,
            'objet_log' => $record->context['objet'] ?? null,
            // traçabilité enrichie : agence, auteur, objet, valeurs avant / après, requête
            'agence_id' => app(\App\Support\AgenceCourante::class)->id(),
            'user_id' => $user?->id,
            'objet_type' => $record->context['objet_type'] ?? null,
            'objet_id' => isset($record->context['objet_id']) ? (string) $record->context['objet_id'] : null,
            'anciennes_valeurs' => isset($record->context['anciennes_valeurs']) ? json_encode($record->context['anciennes_valeurs'], JSON_UNESCAPED_UNICODE) : null,
            'nouvelles_valeurs' => isset($record->context['nouvelles_valeurs']) ? json_encode($record->context['nouvelles_valeurs'], JSON_UNESCAPED_UNICODE) : null,
            'motif_log' => $record->context['motif'] ?? null,
            'url_log' => $request ? substr($request->fullUrl(), 0, 2000) : null,
            'methode_log' => $request?->method(),
            'route_log' => $request?->route()?->getName(),
            'created_at' => $record->datetime->format('Y-m-d H:i:s'),
            'updated_at' => $record->datetime->format('Y-m-d H:i:s'),
        ]);
    }
}
