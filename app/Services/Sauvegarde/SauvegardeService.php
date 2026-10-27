<?php

namespace App\Services\Sauvegarde;

use App\Models\Systeme\Sauvegarde;
use App\Models\Systeme\SauvegardeConfig;
use App\Models\Systeme\SauvegardeDestination;
use App\Helpers\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegardes de la plateforme (reprises de MARIEATTIEKESAAS) : base PostgreSQL (pg_dump, format personnalisé), code de l'application et documents
 * (pièces téléversées + archives GED) réunis dans une archive ZIP chiffrée AES-256 avec un manifeste d'empreintes,
 * conservée localement (rétention) puis envoyée vers chaque destination active (rétention par destination).
 */
class SauvegardeService
{
    /** Dossiers jamais copiés dans l'archive du code. */
    public const EXCLUS = ['node_modules', '.git', 'storage/framework', 'storage/logs', 'storage/app', 'storage/debugbar', 'public/storage', 'bootstrap/cache', 'tests/.phpunit.cache'];

    public static function dossierLocal(): string
    {
        $dossier = storage_path('app/private/sauvegardes');
        File::ensureDirectoryExists($dossier, 0750);

        return $dossier;
    }

    /**
     * @param array<int, string>|null $contenu BASE, CODE, DOCUMENTS (défaut : configuration)
     */
    public static function lancer(string $declenchement = 'MANUEL', ?array $contenu = null): Sauvegarde
    {
        @set_time_limit(0);
        $config = SauvegardeConfig::courante();
        $contenu ??= array_values(array_filter([$config->inclure_base ? 'BASE' : null, $config->inclure_code ? 'CODE' : null, $config->inclure_documents ? 'DOCUMENTS' : null]));
        if (!$contenu) {
            throw new RuntimeException('Rien à sauvegarder : cochez au moins la base, le code ou les documents.');
        }
        // numéro de la plateforme (hors agence) : SAV-2026-00012
        $sauvegarde = Sauvegarde::create(['numero' => 'SAV-' . uniqid(), 'declenchement' => $declenchement, 'contenu' => $contenu,
            'statut' => 'EN_COURS', 'debut_at' => now(), 'lance_par' => Auth::id()]);
        $sauvegarde->update(['numero' => sprintf('SAV-%d-%05d', now()->year, $sauvegarde->id)]);
        $travail = self::dossierLocal() . DIRECTORY_SEPARATOR . 'tmp-' . $sauvegarde->id;
        File::ensureDirectoryExists($travail);

        try {
            $nom = Transferts::PREFIXE . now()->format('Ymd-His') . '-' . strtolower(implode('-', $contenu)) . '.zip';
            $archive = self::dossierLocal() . DIRECTORY_SEPARATOR . $nom;
            $cle = $config->cle();
            $manifeste = ['application' => config('app.name'), 'numero' => $sauvegarde->numero, 'date' => now()->toIso8601String(), 'contenu' => $contenu,
                'base' => config('database.connections.' . config('database.default') . '.database'), 'php' => PHP_VERSION, 'laravel' => app()->version(),
                'derniere_migration' => DB::table('migrations')->orderByDesc('id')->value('migration'), 'fichiers' => []];

            $zip = new ZipArchive();
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Création de l\'archive impossible.');
            }
            $ajouter = function (string $source, string $dansArchive) use ($zip, $cle, &$manifeste) {
                $zip->addFile($source, $dansArchive);
                if ($cle) {
                    $zip->setEncryptionName($dansArchive, ZipArchive::EM_AES_256, $cle);
                }
                $manifeste['fichiers'][$dansArchive] = hash_file('sha256', $source);
            };

            if (in_array('BASE', $contenu, true)) {
                $dump = self::dumpBase($travail, $config);
                $ajouter($dump, 'base/' . basename($dump));
            }
            if (in_array('CODE', $contenu, true)) {
                foreach (self::fichiersCode($config, (bool) $cle) as $relatif => $absolu) {
                    $ajouter($absolu, 'code/' . $relatif);
                }
            }
            if (in_array('DOCUMENTS', $contenu, true)) {
                foreach (self::fichiersDocuments() as $relatif => $absolu) {
                    $ajouter($absolu, 'documents/' . $relatif);
                }
            }
            $codeManifeste = json_encode($manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $zip->addFromString('MANIFESTE.json', $codeManifeste);
            $zip->addFromString('LISEZMOI-RESTAURATION.txt', self::procedureRestauration($cle !== null));
            if (!$zip->close()) {
                throw new RuntimeException('Finalisation de l\'archive impossible (espace disque ?).');
            }
            $manifeste['nb_fichiers'] = count($manifeste['fichiers']);
            $resume = $manifeste;
            unset($resume['fichiers']);

            $sauvegarde->update(['fichier' => $nom, 'chemin_local' => $archive, 'taille_octets' => filesize($archive), 'empreinte_sha256' => hash_file('sha256', $archive),
                'chiffree' => $cle !== null, 'manifeste' => $resume]);

            // envoi vers les destinations actives
            $resultats = [];
            foreach (SauvegardeDestination::where('actif', true)->orderBy('id')->get() as $destination) {
                $debut = microtime(true);
                try {
                    Transferts::deposer($destination, $archive, $nom);
                    $supprimees = Transferts::rotation($destination);
                    $resultats[] = ['id' => $destination->id, 'libelle' => $destination->libelle, 'type' => $destination->type, 'statut' => 'SUCCES',
                        'message' => 'Déposée' . ($supprimees ? " ; $supprimees ancienne(s) archive(s) supprimée(s)" : ''), 'duree_ms' => (int) ((microtime(true) - $debut) * 1000)];
                } catch (\Throwable $e) {
                    $resultats[] = ['id' => $destination->id, 'libelle' => $destination->libelle, 'type' => $destination->type, 'statut' => 'ECHEC',
                        'message' => mb_substr($e->getMessage(), 0, 500), 'duree_ms' => (int) ((microtime(true) - $debut) * 1000)];
                }
            }
            self::rotationLocale($config);
            $echecs = collect($resultats)->where('statut', 'ECHEC')->count();
            $sauvegarde->update(['destinations' => $resultats, 'fin_at' => now(),
                'statut' => $echecs === 0 ? 'SUCCES' : ($echecs < count($resultats) ? 'PARTIEL' : 'ECHEC'),
                'erreur' => $echecs ? $echecs . ' destination(s) en échec' : null]);
        } catch (\Throwable $e) {
            $sauvegarde->update(['statut' => 'ECHEC', 'erreur' => mb_substr($e->getMessage(), 0, 2000), 'fin_at' => now()]);
            report($e);
        } finally {
            File::deleteDirectory($travail);
        }

        $sauvegarde->refresh();
        if ($sauvegarde->statut !== 'SUCCES') {
            self::alerter($sauvegarde);
        }

        return $sauvegarde;
    }

    /** pg_dump au format personnalisé (compressé, restaurable table par table avec pg_restore). */
    protected static function dumpBase(string $travail, SauvegardeConfig $config): string
    {
        $c = config('database.connections.' . config('database.default'));
        if (($c['driver'] ?? '') !== 'pgsql') {
            throw new RuntimeException('Seule la base PostgreSQL est prise en charge par la sauvegarde.');
        }
        $fichier = $travail . DIRECTORY_SEPARATOR . $c['database'] . '-' . now()->format('Ymd-His') . '.dump';
        $commande = [$config->pg_dump_chemin ?: 'pg_dump', '--format=custom', '--no-owner', '--no-privileges', '--compress=6',
            '--host=' . $c['host'], '--port=' . $c['port'], '--username=' . $c['username'], '--file=' . $fichier, $c['database']];
        $resultat = Process::timeout(3600)->env(['PGPASSWORD' => (string) $c['password']])->run($commande);
        if (!$resultat->successful() || !is_file($fichier) || filesize($fichier) === 0) {
            throw new RuntimeException('pg_dump a échoué : ' . mb_substr(trim($resultat->errorOutput() ?: $resultat->output()), 0, 500)
                . ' (vérifiez le chemin de pg_dump dans la configuration des sauvegardes).');
        }

        return $fichier;
    }

    /** Fichiers du code (sans dépendances ni caches) ; .env seulement si l'archive est chiffrée. @return array<string, string> */
    public static function fichiersCode(SauvegardeConfig $config, bool $chiffree): array
    {
        $exclus = self::EXCLUS;
        if (!$config->inclure_vendor) {
            $exclus[] = 'vendor';
        }
        $racine = base_path();
        $fichiers = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS),
            function ($f) use ($racine, $exclus) {
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($racine) + 1));
                foreach ($exclus as $x) {
                    if ($rel === $x || str_starts_with($rel, $x . '/')) {
                        return false;
                    }
                }

                return true;
            }
        ));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($racine) + 1));
                if (!$chiffree && ($rel === '.env' || str_starts_with($rel, '.env.') && $rel !== '.env.example')) {
                    continue;                                           // secrets jamais en clair dans une archive non chiffrée
                }
                $fichiers[$rel] = $f->getPathname();
            }
        }

        return $fichiers;
    }

    /** Pièces téléversées et archives GED (stockage privé), hors sauvegardes elles-mêmes. */
    protected static function fichiersDocuments(): array
    {
        $racine = storage_path('app');
        $fichiers = [];
        if (!is_dir($racine)) {
            return [];
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($racine) + 1));
            if ($f->isFile() && !str_starts_with($rel, 'private/sauvegardes') && !str_contains($rel, '.gitignore')) {
                $fichiers[$rel] = $f->getPathname();
            }
        }

        return $fichiers;
    }

    protected static function rotationLocale(SauvegardeConfig $config): void
    {
        $garder = max(1, (int) $config->retention_locale);
        $anciennes = Sauvegarde::whereNotNull('chemin_local')->orderByDesc('id')->skip($garder)->take(1000)->get();
        foreach ($anciennes as $s) {
            if ($s->chemin_local && is_file($s->chemin_local)) {
                @unlink($s->chemin_local);
            }
            $s->update(['chemin_local' => null]);
        }
    }

    /** Échec / envoi partiel : journal d'audit et journal applicatif (signalés par l'écran Sécurité et le tableau de bord plateforme). */
    protected static function alerter(Sauvegarde $s): void
    {
        $detail = $s->erreur . ' — ' . collect($s->destinations ?? [])->where('statut', 'ECHEC')->map(fn ($d) => $d['libelle'] . ' : ' . $d['message'])->implode(' ; ');
        Log::error('Sauvegarde ' . $s->numero . ' : ' . strtolower($s->statut), ['detail' => $detail]);
        Audit::logSave(['action' => 'SAUVEGARDE ' . $s->statut, 'code_piece' => $s->id, 'menu' => 'SAUVEGARDES', 'etat' => 'Echec', 'objet' => 'PLATEFORME',
            'nouvelles_valeurs' => ['numero' => $s->numero, 'detail' => mb_substr($detail, 0, 1000)]]);
    }

    /** Planification : une sauvegarde est-elle due maintenant ? (commande lancée toutes les 15 minutes) */
    public static function estDue(?\Carbon\Carbon $maintenant = null): bool
    {
        $maintenant ??= now();
        $config = SauvegardeConfig::courante();
        if (!$config->actif) {
            return false;
        }
        [$h, $m] = array_map('intval', explode(':', $config->heure ?: '02:00') + [1 => 0]);
        $prevue = $maintenant->copy()->setTime($h, $m);
        if ($maintenant->lt($prevue)) {
            return false;
        }
        if ($config->frequence === 'HEBDOMADAIRE' && $maintenant->dayOfWeekIso !== (int) $config->jour_semaine) {
            return false;
        }

        return !Sauvegarde::where('declenchement', 'PLANIFIEE')->where('debut_at', '>=', $prevue)->exists();
    }

    public static function procedureRestauration(bool $chiffree): string
    {
        return "RESTAURATION D'UNE SAUVEGARDE DE LA PLATEFORME IMMOBILIER\r\n=====================================\r\n\r\n"
            . ($chiffree ? "1. Ouvrir l'archive avec la clé de chiffrement définie dans Configuration > Sauvegardes (7-Zip : AES-256).\r\n" : "1. Décompresser l'archive.\r\n")
            . "2. Vérifier l'intégrité : comparer les empreintes SHA-256 des fichiers avec MANIFESTE.json.\r\n"
            . "3. Base de données (dossier base/) : créer une base vide puis\r\n"
            . "     pg_restore --clean --if-exists --no-owner --dbname=<base> base/<fichier>.dump\r\n"
            . "4. Code (dossier code/) : copier sur le serveur, puis « composer install --no-dev » (si vendor absent),\r\n"
            . "     vérifier le fichier .env (APP_KEY identique, sinon les secrets chiffrés sont illisibles), « php artisan migrate --force ».\r\n"
            . "5. Documents (dossier documents/) : recopier dans storage/app/ (pièces jointes et archives GED).\r\n"
            . "6. « php artisan optimize:clear », puis contrôler la connexion, une fiche de bien, un reçu et un document archivé (GED : intégrité vérifiée).\r\n";
    }
}
