<?php

namespace App\Models\Integration;

use App\Support\AgenceCourante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Canal d'envoi configuré à l'écran. agence_id NULL = configuration de la plateforme.
 * Les secrets (mots de passe, clés d'API) sont chiffrés avec la clé de l'application et jamais réaffichés.
 */
class Canal extends Model
{
    protected $table = 'canaux';

    protected $fillable = ['agence_id', 'code', 'fournisseur', 'actif', 'sandbox', 'parametres'];

    protected $hidden = ['secrets'];

    protected $attributes = ['actif' => false, 'sandbox' => false];

    protected function casts(): array
    {
        return ['parametres' => 'array', 'actif' => 'boolean', 'sandbox' => 'boolean', 'dernier_test_at' => 'datetime'];
    }

    /** @return array<string, string> */
    public function secretsDechiffres(): array
    {
        if (!$this->secrets) {
            return [];
        }
        try {
            return (array) json_decode(Crypt::decryptString($this->secrets), true);
        } catch (\Throwable) {
            return [];
        }
    }

    public function definirSecrets(array $secrets): void
    {
        $this->secrets = $secrets ? Crypt::encryptString(json_encode($secrets)) : null;
    }

    /** Paramètre ou secret (les secrets priment). */
    public function valeur(string $cle, mixed $defaut = null): mixed
    {
        $valeur = $this->secretsDechiffres()[$cle] ?? ($this->parametres[$cle] ?? null);

        return $valeur === null || $valeur === '' ? $defaut : $valeur;
    }

    public function aSecret(string $cle): bool
    {
        return !empty($this->secretsDechiffres()[$cle]);
    }

    /** Canal de l'écran de configuration : celui de l'agence courante, ou celui de la plateforme hors agence. */
    public static function configuration(string $code): ?self
    {
        return static::where('code', $code)->where('agence_id', app(AgenceCourante::class)->id())->first();
    }

    /** Canal utilisé pour envoyer : celui de l'agence s'il est actif, sinon celui de la plateforme. */
    public static function actif(string $code): ?self
    {
        $agenceId = app(AgenceCourante::class)->id();

        return static::where('code', $code)->where('actif', true)
            ->where(fn ($q) => $q->whereNull('agence_id')->when($agenceId, fn ($q) => $q->orWhere('agence_id', $agenceId)))
            ->orderByRaw('agence_id IS NULL')->first();
    }
}
