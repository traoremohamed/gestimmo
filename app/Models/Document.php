<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document de la GED : fichier téléversé (tout format) ou document généré par l'application (archivé avec sa signature QR).
 */
class Document extends Model
{
    use AppartientAAgence, SoftDeletes;

    /** Aperçu dans le navigateur (les autres formats sont téléchargés). */
    public const APERCU = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    protected $fillable = [
        'agence_id', 'numero', 'titre', 'categorie', 'description', 'documentable_type', 'documentable_id', 'tiers_id', 'source', 'disque', 'chemin',
        'nom_original', 'extension', 'mime', 'taille', 'empreinte_sha256', 'qr_jeton_id', 'etat_source', 'version', 'document_parent_id', 'confidentiel', 'user_id',
    ];

    protected $attributes = ['source' => 'televerse', 'version' => 1, 'confidentiel' => false, 'categorie' => 'AUTRE', 'disque' => 'local'];

    protected $casts = ['confidentiel' => 'boolean'];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tiers(): BelongsTo
    {
        return $this->belongsTo(Tiers::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jeton(): BelongsTo
    {
        return $this->belongsTo(QrJeton::class, 'qr_jeton_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'document_parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'document_parent_id')->orderByDesc('version');
    }

    public function apercuPossible(): bool
    {
        return in_array($this->mime, self::APERCU, true);
    }

    public function icone(): string
    {
        return match (true) {
            str_starts_with((string) $this->mime, 'image/') => 'ti-photo',
            $this->extension === 'pdf' => 'ti-file-type-pdf',
            in_array($this->extension, ['xls', 'xlsx', 'csv', 'ods'], true) => 'ti-file-spreadsheet',
            in_array($this->extension, ['doc', 'docx', 'odt', 'rtf'], true) => 'ti-file-type-doc',
            in_array($this->extension, ['zip', 'rar', '7z'], true) => 'ti-file-zip',
            default => 'ti-file',
        };
    }

    public function tailleLisible(): string
    {
        $o = (int) $this->taille;

        return match (true) {
            $o >= 1048576 => number_format($o / 1048576, 1, ',', ' ') . ' Mo',
            $o >= 1024 => number_format($o / 1024, 0, ',', ' ') . ' Ko',
            default => $o . ' o',
        };
    }
}
