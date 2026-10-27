<?php

namespace App\Models;

use App\Models\Concerns\AppartientAAgence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Fichier rattaché à un modèle (photos et plans d'un bien, documents…).
 * Les photos publiables sont sur le disque public, les documents sur le disque privé.
 */
class Media extends Model
{
    use AppartientAAgence;

    protected $table = 'medias';

    protected $fillable = ['agence_id', 'mediable_type', 'mediable_id', 'collection', 'disque', 'chemin', 'nom_original', 'mime', 'taille', 'libelle', 'ordre', 'created_by'];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): ?string
    {
        return $this->disque === 'public' ? Storage::disk('public')->url($this->chemin) : route('medias.telecharger', $this);
    }

    public function estImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    protected static function booted(): void
    {
        static::deleted(fn (Media $media) => Storage::disk($media->disque)->delete($media->chemin));
    }
}
