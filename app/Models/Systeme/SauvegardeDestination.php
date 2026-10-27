<?php

namespace App\Models\Systeme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Table sys_sauvegarde_destinations — où envoyer les archives des sauvegardes. Les secrets sont chiffrés et jamais réaffichés.
 */
class SauvegardeDestination extends Model
{
    protected $table = 'sys_sauvegarde_destinations';

    protected $guarded = ['id'];

    protected $hidden = ['secrets'];

    /** type => [libellé, champs non sensibles, champs secrets] */
    public const TYPES = [
        'LOCAL' => ['Dossier local ou réseau (NAS, disque externe, partage \\\\serveur\\dossier)', ['chemin'], []],
        'SFTP' => ['Serveur distant (SFTP / SSH)', ['hote', 'port', 'utilisateur', 'dossier'], ['mot_de_passe', 'cle_privee']],
        'WEBDAV' => ['WebDAV (Nextcloud, ownCloud, Synology, Box…)', ['url', 'utilisateur'], ['mot_de_passe']],
        'GOOGLE_DRIVE' => ['Google Drive (lien d\'un dossier)', ['lien_dossier', 'mode_auth', 'client_id'], ['compte_service_json', 'client_secret', 'refresh_token']],
    ];

    protected function casts(): array
    {
        return ['parametres' => 'array', 'actif' => 'boolean', 'dernier_test_at' => 'datetime'];
    }

    public function p(string $cle, $defaut = null)
    {
        return $this->parametres[$cle] ?? $defaut;
    }

    public function secret(string $cle): ?string
    {
        $tous = $this->secrets ? json_decode(Crypt::decryptString($this->secrets), true) : [];

        return $tous[$cle] ?? null;
    }

    /** Remplace les secrets fournis (un champ vide conserve la valeur enregistrée). */
    public function definirSecrets(array $nouveaux): void
    {
        $tous = $this->secrets ? json_decode(Crypt::decryptString($this->secrets), true) : [];
        foreach ($nouveaux as $k => $v) {
            if ($v !== null && $v !== '') {
                $tous[$k] = $v;
            }
        }
        $this->secrets = $tous ? Crypt::encryptString(json_encode($tous)) : null;
    }

    public function aSecret(string $cle): bool
    {
        return $this->secret($cle) !== null;
    }
}
