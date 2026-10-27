<?php

namespace App\Services\Sauvegarde;

use App\Models\Systeme\SauvegardeDestination;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Envoi des archives vers les destinations (D39) : dossier local / réseau, SFTP, WebDAV, Google Drive.
 * Chaque pilote sait déposer un fichier, lister les archives « immobilier-*.zip » (rotation) et en supprimer.
 */
class Transferts
{
    public const PREFIXE = 'immobilier-';

    public static function deposer(SauvegardeDestination $d, string $fichierLocal, string $nom): void
    {
        match ($d->type) {
            'LOCAL' => self::localDeposer($d, $fichierLocal, $nom),
            'SFTP' => self::sftp($d)->put(self::sftpChemin($d, $nom), $fichierLocal, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)
                ?: throw new RuntimeException('Échec de l\'envoi SFTP.'),
            'WEBDAV' => self::webdavDeposer($d, $fichierLocal, $nom),
            'GOOGLE_DRIVE' => self::driveDeposer($d, $fichierLocal, $nom),
            default => throw new RuntimeException('Type de destination inconnu.'),
        };
    }

    /** Supprime les archives au-delà de la rétention (les plus anciennes d'abord). @return int nombre supprimé */
    public static function rotation(SauvegardeDestination $d): int
    {
        $garder = max(1, (int) $d->retention);
        $fichiers = self::lister($d);                                   // [nom => identifiant], triés du plus ancien au plus récent
        $aSupprimer = array_slice($fichiers, 0, max(0, count($fichiers) - $garder), true);
        foreach ($aSupprimer as $nom => $id) {
            self::supprimer($d, $nom, $id);
        }

        return count($aSupprimer);
    }

    /** Test de connexion : dépôt puis suppression d'un petit fichier. */
    public static function tester(SauvegardeDestination $d): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'immobilier');
        file_put_contents($tmp, 'Test de sauvegarde ' . now()->toIso8601String());
        $nom = self::PREFIXE . 'test-connexion.txt';
        try {
            self::deposer($d, $tmp, $nom);
            $ids = self::lister($d, '.txt');
            self::supprimer($d, $nom, $ids[$nom] ?? null);
        } finally {
            @unlink($tmp);
        }

        return 'Connexion réussie : fichier de test déposé puis supprimé.';
    }

    // ------------------------------------------------------------------ listes / suppression

    protected static function lister(SauvegardeDestination $d, string $extension = '.zip'): array
    {
        $estArchive = fn ($n) => Str::startsWith($n, self::PREFIXE) && Str::endsWith($n, $extension);
        switch ($d->type) {
            case 'LOCAL':
                $liste = collect(glob(rtrim(self::dossierLocal($d), '/\\') . DIRECTORY_SEPARATOR . self::PREFIXE . '*' . $extension) ?: [])
                    ->sortBy(fn ($f) => filemtime($f))->mapWithKeys(fn ($f) => [basename($f) => $f]);

                return $liste->all();
            case 'SFTP':
                $sftp = self::sftp($d);
                $brut = $sftp->rawlist(self::sftpDossier($d)) ?: [];

                return collect($brut)->filter(fn ($i, $n) => $estArchive((string) $n))->sortBy(fn ($i) => $i['mtime'] ?? 0)
                    ->mapWithKeys(fn ($i, $n) => [$n => $n])->all();
            case 'WEBDAV':
                $r = Http::withBasicAuth((string) $d->p('utilisateur'), (string) $d->secret('mot_de_passe'))->withHeaders(['Depth' => '1'])
                    ->send('PROPFIND', self::webdavUrl($d, ''));
                if ($r->failed() && $r->status() !== 207) {
                    throw new RuntimeException('WebDAV : liste impossible (HTTP ' . $r->status() . ').');
                }
                preg_match_all('#<[a-z]*:?href>([^<]+)</[a-z]*:?href>#i', $r->body(), $m);

                return collect($m[1])->map(fn ($h) => rawurldecode(basename(rtrim($h, '/'))))->filter($estArchive)->sort()->mapWithKeys(fn ($n) => [$n => $n])->all();
            case 'GOOGLE_DRIVE':
                $r = Http::withToken(self::jetonDrive($d))->get('https://www.googleapis.com/drive/v3/files', [
                    'q' => "'" . self::dossierDrive($d) . "' in parents and trashed = false and name contains '" . self::PREFIXE . "'",
                    'orderBy' => 'createdTime', 'fields' => 'files(id,name,createdTime)', 'pageSize' => 1000,
                    'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true']);
                self::verifier($r, 'Google Drive : liste');

                return collect($r->json('files', []))->filter(fn ($f) => $estArchive($f['name']))->mapWithKeys(fn ($f) => [$f['name'] => $f['id']])->all();
        }

        return [];
    }

    protected static function supprimer(SauvegardeDestination $d, string $nom, $id): void
    {
        match ($d->type) {
            'LOCAL' => @unlink($id ?? rtrim(self::dossierLocal($d), '/\\') . DIRECTORY_SEPARATOR . $nom),
            'SFTP' => self::sftp($d)->delete(self::sftpChemin($d, $nom)),
            'WEBDAV' => Http::withBasicAuth((string) $d->p('utilisateur'), (string) $d->secret('mot_de_passe'))->delete(self::webdavUrl($d, $nom)),
            'GOOGLE_DRIVE' => $id ? Http::withToken(self::jetonDrive($d))->delete('https://www.googleapis.com/drive/v3/files/' . $id . '?supportsAllDrives=true') : null,
            default => null,
        };
    }

    // ------------------------------------------------------------------ LOCAL / RÉSEAU

    protected static function dossierLocal(SauvegardeDestination $d): string
    {
        $dossier = trim((string) $d->p('chemin'));
        if ($dossier === '') {
            throw new RuntimeException('Chemin du dossier non renseigné.');
        }

        return $dossier;
    }

    protected static function localDeposer(SauvegardeDestination $d, string $fichier, string $nom): void
    {
        $dossier = self::dossierLocal($d);
        if (!is_dir($dossier) && !@mkdir($dossier, 0750, true)) {
            throw new RuntimeException("Dossier inaccessible : $dossier");
        }
        if (!@copy($fichier, rtrim($dossier, '/\\') . DIRECTORY_SEPARATOR . $nom)) {
            throw new RuntimeException("Copie impossible vers $dossier (droits d'écriture ?).");
        }
    }

    // ------------------------------------------------------------------ SFTP

    protected static array $connexionsSftp = [];

    protected static function sftp(SauvegardeDestination $d): \phpseclib3\Net\SFTP
    {
        if (isset(self::$connexionsSftp[$d->id]) && self::$connexionsSftp[$d->id]->isConnected()) {
            return self::$connexionsSftp[$d->id];
        }
        if (!class_exists(\phpseclib3\Net\SFTP::class)) {
            throw new RuntimeException('Destination SFTP indisponible : installez la bibliothèque (composer require phpseclib/phpseclib:^3.0).');
        }
        $sftp = new \phpseclib3\Net\SFTP((string) $d->p('hote'), (int) ($d->p('port') ?: 22), 30);
        $cle = $d->secret('cle_privee');
        $auth = $cle ? \phpseclib3\Crypt\PublicKeyLoader::load($cle, $d->secret('mot_de_passe') ?: false) : (string) $d->secret('mot_de_passe');
        if (!$sftp->login((string) $d->p('utilisateur'), $auth)) {
            throw new RuntimeException('SFTP : authentification refusée par ' . $d->p('hote') . '.');
        }
        $dossier = self::sftpDossier($d);
        if (!$sftp->is_dir($dossier)) {
            $sftp->mkdir($dossier, -1, true);
        }

        return self::$connexionsSftp[$d->id] = $sftp;
    }

    protected static function sftpDossier(SauvegardeDestination $d): string
    {
        return rtrim((string) ($d->p('dossier') ?: '.'), '/');
    }

    protected static function sftpChemin(SauvegardeDestination $d, string $nom): string
    {
        return self::sftpDossier($d) . '/' . $nom;
    }

    // ------------------------------------------------------------------ WEBDAV

    protected static function webdavUrl(SauvegardeDestination $d, string $nom): string
    {
        return rtrim((string) $d->p('url'), '/') . '/' . rawurlencode($nom);
    }

    protected static function webdavDeposer(SauvegardeDestination $d, string $fichier, string $nom): void
    {
        $flux = fopen($fichier, 'rb');
        try {
            $r = Http::withBasicAuth((string) $d->p('utilisateur'), (string) $d->secret('mot_de_passe'))->timeout(3600)
                ->withBody($flux, 'application/octet-stream')->put(self::webdavUrl($d, $nom));
        } finally {
            is_resource($flux) && fclose($flux);
        }
        self::verifier($r, 'WebDAV : dépôt');
    }

    // ------------------------------------------------------------------ GOOGLE DRIVE

    /** Identifiant du dossier extrait du lien partagé (…/drive/folders/{id} ou …?id={id}) ou saisi directement. */
    public static function dossierDrive(SauvegardeDestination $d): string
    {
        $lien = trim((string) $d->p('lien_dossier'));
        if (preg_match('#/folders/([A-Za-z0-9_-]{10,})#', $lien, $m) || preg_match('#[?&]id=([A-Za-z0-9_-]{10,})#', $lien, $m)) {
            return $m[1];
        }
        if (preg_match('#^[A-Za-z0-9_-]{10,}$#', $lien)) {
            return $lien;
        }
        throw new RuntimeException('Lien de dossier Google Drive invalide (attendu : https://drive.google.com/drive/folders/…).');
    }

    protected static array $jetons = [];

    protected static function jetonDrive(SauvegardeDestination $d): string
    {
        if (isset(self::$jetons[$d->id]) && self::$jetons[$d->id][1] > time() + 60) {
            return self::$jetons[$d->id][0];
        }
        if (($d->p('mode_auth') ?: 'COMPTE_SERVICE') === 'OAUTH') {
            $r = Http::asForm()->post('https://oauth2.googleapis.com/token', ['grant_type' => 'refresh_token', 'client_id' => $d->p('client_id'),
                'client_secret' => $d->secret('client_secret'), 'refresh_token' => $d->secret('refresh_token')]);
        } else {
            $compte = json_decode((string) $d->secret('compte_service_json'), true);
            if (!is_array($compte) || empty($compte['private_key']) || empty($compte['client_email'])) {
                throw new RuntimeException('Compte de service Google invalide (fichier JSON attendu).');
            }
            $b64 = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
            $entete = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $charge = $b64(json_encode(['iss' => $compte['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600]));
            openssl_sign($entete . '.' . $charge, $signature, $compte['private_key'], OPENSSL_ALGO_SHA256);
            $r = Http::asForm()->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $entete . '.' . $charge . '.' . $b64($signature)]);
        }
        self::verifier($r, 'Google : authentification');
        self::$jetons[$d->id] = [(string) $r->json('access_token'), time() + (int) $r->json('expires_in', 3600)];

        return self::$jetons[$d->id][0];
    }

    /** Envoi « reprenable » par morceaux de 8 Mo (archives volumineuses). */
    protected static function driveDeposer(SauvegardeDestination $d, string $fichier, string $nom): void
    {
        $taille = filesize($fichier);
        $init = Http::withToken(self::jetonDrive($d))->withHeaders(['X-Upload-Content-Type' => 'application/octet-stream', 'X-Upload-Content-Length' => (string) $taille])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true', ['name' => $nom, 'parents' => [self::dossierDrive($d)]]);
        self::verifier($init, 'Google Drive : ouverture de l\'envoi');
        $url = $init->header('Location');
        if (!$url) {
            throw new RuntimeException('Google Drive : adresse d\'envoi non fournie.');
        }
        $morceau = 8 * 1024 * 1024;
        $flux = fopen($fichier, 'rb');
        try {
            for ($debut = 0; $debut < $taille || $taille === 0; $debut += $morceau) {
                $donnees = $taille === 0 ? '' : fread($flux, $morceau);
                $fin = $taille === 0 ? 0 : $debut + strlen($donnees) - 1;
                $r = Http::withToken(self::jetonDrive($d))->timeout(600)
                    ->withHeaders(['Content-Range' => $taille === 0 ? 'bytes */0' : "bytes $debut-$fin/$taille"])
                    ->withBody($donnees, 'application/octet-stream')->put($url);
                if ($r->status() === 308) {
                    continue;                                           // morceau reçu, la suite est attendue
                }
                self::verifier($r, 'Google Drive : envoi');
                break;
            }
        } finally {
            fclose($flux);
        }
    }

    protected static function verifier(\Illuminate\Http\Client\Response $r, string $contexte): void
    {
        if ($r->failed()) {
            $message = $r->json('error.message') ?? $r->json('error_description') ?? Str::limit(strip_tags($r->body()), 200);
            throw new RuntimeException("$contexte refusé (HTTP {$r->status()}) : $message");
        }
    }
}
