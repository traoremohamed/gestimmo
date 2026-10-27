<?php

namespace App\Services\Securite;

use App\Models\Systeme\Sauvegarde;
use App\Models\Systeme\SauvegardeConfig;
use App\Models\Systeme\SauvegardeDestination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Diagnostic de sécurité de l'installation (repris de MARIEATTIEKESAAS) : écran Plateforme › Sécurité
 * et commande « php artisan immobilier:securite ».
 */
class DiagnosticSecurite
{
    /** Mots de passe connus (démonstration, valeur par défaut du super administrateur) à ne jamais garder en service. */
    public const MOTS_DE_PASSE_CONNUS = ['Demo@2026!', 'Admin@2026!'];

    /** @return array<int, array{libelle: string, ok: bool, gravite: string, detail: string}> */
    public static function controles(): array
    {
        $c = [];
        $ajouter = function (string $libelle, bool $ok, string $gravite, string $detail) use (&$c) {
            $c[] = compact('libelle', 'ok', 'gravite', 'detail');
        };
        $prod = app()->environment('production');

        // application
        $ajouter('Mode débogage désactivé', !config('app.debug'), 'CRITIQUE', config('app.debug') ? 'APP_DEBUG=true : les erreurs exposent le code et la configuration. Mettre APP_DEBUG=false en production.' : 'OK');
        $ajouter('Environnement de production', $prod, 'ALERTE', $prod ? 'OK' : 'APP_ENV=' . app()->environment() . ' : mettre APP_ENV=production sur le serveur réel.');
        $ajouter('Clé de chiffrement de l\'application', strlen((string) config('app.key')) > 20, 'CRITIQUE', config('app.key') ? 'Présente (la sauvegarder : sans elle, les secrets chiffrés sont perdus).' : 'APP_KEY absente : php artisan key:generate.');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $ajouter('Accès en HTTPS', $https, 'CRITIQUE', $https ? 'OK' : 'APP_URL n\'est pas en https:// : mots de passe, contrats et pièces d\'identité circulent en clair. Installer un certificat (Let\'s Encrypt).');
        $ajouter('Cookie de session sécurisé', (bool) config('session.secure'), 'ALERTE', config('session.secure') ? 'OK' : 'SESSION_SECURE_COOKIE=true (avec HTTPS).');
        $ajouter('Session chiffrée', (bool) config('session.encrypt'), 'ALERTE', config('session.encrypt') ? 'OK' : 'SESSION_ENCRYPT=true recommandé.');
        $ajouter('Expiration de session raisonnable', (int) config('session.lifetime') <= 120, 'ALERTE', 'Durée : ' . (int) config('session.lifetime') . ' min (≤ 120 recommandé sur des postes partagés).');
        $ssl = config('database.connections.pgsql.sslmode');
        $local = in_array(config('database.connections.pgsql.host'), ['127.0.0.1', 'localhost', '::1'], true);
        $ajouter('Connexion chiffrée à la base', $local || in_array($ssl, ['require', 'verify-ca', 'verify-full'], true), 'ALERTE',
            $local ? 'Base locale (pas de transit réseau).' : 'DB_SSLMODE=' . $ssl . ' : utiliser require ou verify-full pour une base distante.');
        $ajouter('Journal sans niveau debug', config('logging.channels.' . config('logging.default') . '.level', 'debug') !== 'debug' || !$prod, 'INFO', 'LOG_LEVEL=warning ou error en production.');

        // comptes et droits
        $connus = 0;
        foreach (DB::table('users')->where('flag_actif_users', true)->orderByDesc('est_super_admin')->limit(100)->get(['password']) as $u) {
            foreach (self::MOTS_DE_PASSE_CONNUS as $mdp) {
                if ($u->password && Hash::check($mdp, $u->password)) {
                    $connus++;
                    break;
                }
            }
        }
        $ajouter('Aucun mot de passe connu (démonstration, par défaut)', $connus === 0, 'CRITIQUE', $connus ? "$connus compte(s) actif(s) avec un mot de passe de démonstration ou par défaut : les désactiver ou changer leur mot de passe avant la mise en service." : 'OK');
        $sansChangement = DB::table('users')->where('flag_actif_users', true)->where(fn ($q) => $q->where('flag_mdp', false)->orWhereNull('flag_mdp'))->count();
        $ajouter('Mots de passe initiaux changés', $sansChangement === 0, 'INFO', $sansChangement ? "$sansChangement compte(s) n'ont pas encore changé leur mot de passe initial (changement imposé à la 1re connexion)." : 'OK');
        $superAdmins = DB::table('users')->where('est_super_admin', true)->where('flag_actif_users', true)->count();
        $ajouter('Super administrateurs limités', $superAdmins <= 2, 'ALERTE', "$superAdmins super administrateur(s) actif(s) (2 maximum recommandé, nominatifs).");
        $tentatives = (int) config('rbac.securite.tentatives_max');
        $ajouter('Verrouillage après échecs de connexion', $tentatives > 0 && $tentatives <= 10, 'ALERTE', $tentatives ? "Compte verrouillé après $tentatives échecs, " . (int) config('rbac.securite.verrouillage_minutes') . ' min.' : 'Désactivé : RBAC_TENTATIVES_MAX=5 recommandé.');
        $dormants = DB::table('users')->where('flag_actif_users', true)->where('est_super_admin', false)
            ->where(fn ($q) => $q->where('derniere_connexion_at', '<', now()->subDays(90))->orWhere(fn ($w) => $w->whereNull('derniere_connexion_at')->where('created_at', '<', now()->subDays(90))))->count();
        $ajouter('Pas de compte dormant', $dormants === 0, 'INFO', $dormants ? "$dormants compte(s) actif(s) sans connexion depuis 90 jours : les désactiver ou fixer une date de fin d'accès." : 'OK');
        $audit = DB::selectOne("select count(*) as n from pg_trigger t join pg_class c on c.oid = t.tgrelid join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'audit' and not t.tgisinternal");
        $ajouter('Journal d\'audit inaltérable', ($audit->n ?? 0) > 0, 'ALERTE', ($audit->n ?? 0) > 0 ? 'OK (modification et suppression interdites par la base).' : 'Déclencheur de protection du journal absent : relancer les migrations.');

        // sauvegardes
        $config = SauvegardeConfig::courante();
        $derniere = Sauvegarde::where('statut', 'SUCCES')->latest('id')->first();
        $ajouter('Sauvegarde automatique active', (bool) $config->actif, 'CRITIQUE', $config->actif ? 'OK' : 'Activer la sauvegarde planifiée (Plateforme › Sauvegardes).');
        $ajouter('Sauvegarde réussie depuis moins de 26 h', $derniere && $derniere->debut_at->gt(now()->subHours(26)), 'CRITIQUE',
            $derniere ? 'Dernière : ' . $derniere->debut_at->format('d/m/Y H:i') : 'Aucune sauvegarde réussie.');
        $ajouter('Sauvegardes chiffrées', (bool) $config->cle_chiffrement, 'ALERTE', $config->cle_chiffrement ? 'OK (AES-256)' : 'Définir une clé de chiffrement des archives.');
        $externes = SauvegardeDestination::where('actif', true)->count();
        $ajouter('Copie hors du serveur', $externes > 0, 'CRITIQUE', $externes ? "$externes destination(s) active(s)." : 'Aucune destination externe : une panne ou un rançongiciel détruit données ET sauvegardes.');

        // fichiers et envois
        $ajouter('Fichier .env hors du dossier public', !is_file(public_path('.env')), 'CRITIQUE', is_file(public_path('.env')) ? 'Un .env est présent dans public/ : le supprimer immédiatement.' : 'OK');
        $ajouter('Pièces et documents GED en stockage privé', config('filesystems.disks.local.root') !== public_path(), 'CRITIQUE', 'Pièces d\'identité, contrats et archives GED sont servis après contrôle des droits, jamais par une URL publique.');
        $sandbox = DB::table('canaux')->where('actif', true)->where('sandbox', true)->count();
        $ajouter('Canaux d\'envoi en production', $sandbox === 0 || !$prod, 'INFO', $sandbox ? "$sandbox canal(aux) e-mail / SMS / WhatsApp encore en mode test (sandbox)." : 'OK');

        return $c;
    }

    /** Score : pourcentage de contrôles réussis, pondéré par la gravité. */
    public static function score(array $controles): int
    {
        $poids = ['CRITIQUE' => 3, 'ALERTE' => 2, 'INFO' => 1];
        $total = array_sum(array_map(fn ($c) => $poids[$c['gravite']] ?? 1, $controles));
        $ok = array_sum(array_map(fn ($c) => $c['ok'] ? ($poids[$c['gravite']] ?? 1) : 0, $controles));

        return $total ? (int) round(100 * $ok / $total) : 100;
    }
}
