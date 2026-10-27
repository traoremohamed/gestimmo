<?php

namespace App\Models\Systeme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Table sys_sauvegarde_config — paramètres des sauvegardes de la plateforme, une seule ligne.
 */
class SauvegardeConfig extends Model
{
    protected $table = 'sys_sauvegarde_config';

    protected $guarded = ['id'];

    protected $hidden = ['cle_chiffrement'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'inclure_base' => 'boolean', 'inclure_code' => 'boolean', 'inclure_documents' => 'boolean', 'inclure_vendor' => 'boolean'];
    }

    public static function courante(): self
    {
        return static::query()->first() ?? tap(static::create([]))->refresh();
    }

    public function cle(): ?string
    {
        return $this->cle_chiffrement ? Crypt::decryptString($this->cle_chiffrement) : null;
    }

    public function definirCle(?string $cle): void
    {
        $this->cle_chiffrement = $cle ? Crypt::encryptString($cle) : null;
    }
}
