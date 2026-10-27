<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Jeton de la signature QR d'un document : le QR ne contient qu'une URL portant ce jeton aléatoire.
 * Volontairement sans portée d'agence : la vérification est publique.
 */
class QrJeton extends Model
{
    protected $table = 'qr_jetons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['emis_at' => 'datetime', 'revoque_at' => 'datetime', 'derniere_verification_at' => 'datetime', 'montant' => 'decimal:2'];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function objet(): MorphTo
    {
        return $this->morphTo();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withoutGlobalScopes()->withTrashed();
    }
}
