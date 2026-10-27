<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Téléchargement des fichiers privés (documents) : jamais exposés dans public/.
 * Le scope agence du modèle garantit qu'un fichier d'une autre agence renvoie 404.
 */
class MediaController extends Controller
{
    public function telecharger(Media $media)
    {
        abort_unless(Storage::disk($media->disque)->exists($media->chemin), 404);

        return Storage::disk($media->disque)->response($media->chemin, $media->nom_original);
    }
}
